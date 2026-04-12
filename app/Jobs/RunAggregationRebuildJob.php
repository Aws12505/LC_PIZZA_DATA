<?php

namespace App\Jobs;

use App\Services\Aggregation\AggregationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunAggregationRebuildJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 21600; // 6 hours
    public $tries = 1;

    public function __construct(
        protected string $aggregationId,
        protected string $startDate,
        protected string $endDate,
        protected string $type = 'all'
    ) {
    }

    public function handle(AggregationService $service): void
    {
        ini_set('memory_limit', '2G');

        $lock = Cache::lock('aggregation_rebuild_lock', 60 * 60 * 12);

        if (!$lock->get()) {
            $this->updateProgress('failed', [
                'message' => 'Another aggregation rebuild is already running.',
                'updated_at' => now()->toISOString(),
            ]);
            return;
        }

        try {
            $start = Carbon::parse($this->startDate)->startOfDay();
            $end = Carbon::parse($this->endDate)->startOfDay();

            $units = $this->buildExecutionPlan($start, $end, $this->type);

            $processed = 0;
            $successful = 0;
            $failed = 0;
            $failedUnits = [];

            $this->updateProgress('processing', [
                'total' => count($units),
                'processed' => 0,
                'successful' => 0,
                'failed' => 0,
                'current_unit' => null,
                'failed_units' => [],
                'started_at' => now()->toISOString(),
            ]);

            foreach ($units as $unit) {
                $this->updateProgress('processing', [
                    'total' => count($units),
                    'processed' => $processed,
                    'successful' => $successful,
                    'failed' => $failed,
                    'current_unit' => $unit,
                ]);

                try {
                    $this->runUnit($service, $unit);
                    $successful++;
                } catch (\Throwable $e) {
                    $failed++;

                    $failedUnit = [
                        'stage' => $unit['stage'],
                        'label' => $unit['label'],
                        'error' => $e->getMessage(),
                        'failed_at' => now()->toISOString(),
                    ];

                    $failedUnits[] = $failedUnit;

                    Log::error('Aggregation rebuild unit failed', [
                        'aggregation_id' => $this->aggregationId,
                        'type' => $this->type,
                        'unit' => $unit,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    // Important: do NOT throw. Continue normally.
                }

                $processed++;

                $this->updateProgress('processing', [
                    'total' => count($units),
                    'processed' => $processed,
                    'successful' => $successful,
                    'failed' => $failed,
                    'current_unit' => $processed < count($units) ? $units[$processed] : null,
                    'failed_units' => array_slice($failedUnits, -50),
                ]);
            }

            $this->updateProgress('completed', [
                'total' => count($units),
                'processed' => $processed,
                'successful' => $successful,
                'failed' => $failed,
                'current_unit' => null,
                'failed_units' => array_slice($failedUnits, -50),
                'completed_at' => now()->toISOString(),
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    protected function buildExecutionPlan(Carbon $start, Carbon $end, string $type): array
    {
        return match ($type) {
            'hourly' => $this->buildDailyUnits($start, $end, 'hourly'),
            'daily' => $this->buildDailyUnits($start, $end, 'daily'),
            'weekly' => $this->buildWeeklyUnits($start, $end),
            'monthly' => $this->buildMonthlyUnits($start, $end),
            'quarterly' => $this->buildQuarterlyUnits($start, $end),
            'yearly' => $this->buildYearlyUnits($start, $end),
            'all' => array_merge(
                $this->buildDailyUnits($start, $end, 'hourly'),
                $this->buildDailyUnits($start, $end, 'daily'),
                $this->buildWeeklyUnits($start, $end),
                $this->buildMonthlyUnits($start, $end),
                $this->buildQuarterlyUnits($start, $end),
                $this->buildYearlyUnits($start, $end),
            ),
            default => [],
        };
    }

    protected function buildDailyUnits(Carbon $start, Carbon $end, string $stage): array
    {
        $units = [];
        $current = $start->copy();

        while ($current <= $end) {
            $units[] = [
                'stage' => $stage,
                'date' => $current->toDateString(),
                'label' => "{$stage}:{$current->toDateString()}",
            ];
            $current->addDay();
        }

        return $units;
    }

    protected function buildWeeklyUnits(Carbon $start, Carbon $end): array
    {
        $units = [];
        $seen = [];

        $current = $start->copy();
        while ($current <= $end) {
            $weekStart = $current->copy()->startOfWeek(Carbon::TUESDAY);
            $weekEnd = $current->copy()->endOfWeek(Carbon::MONDAY);
            $key = $weekStart->toDateString() . '_' . $weekEnd->toDateString();

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $units[] = [
                    'stage' => 'weekly',
                    'start_date' => $weekStart->toDateString(),
                    'end_date' => $weekEnd->toDateString(),
                    'label' => "weekly:{$weekStart->toDateString()}->{$weekEnd->toDateString()}",
                ];
            }

            $current->addDay();
        }

        return $units;
    }

    protected function buildMonthlyUnits(Carbon $start, Carbon $end): array
    {
        $units = [];
        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();

        while ($cursor <= $last) {
            $units[] = [
                'stage' => 'monthly',
                'year' => (int) $cursor->year,
                'month' => (int) $cursor->month,
                'label' => 'monthly:' . $cursor->format('Y-m'),
            ];

            $cursor->addMonth();
        }

        return $units;
    }

    protected function buildQuarterlyUnits(Carbon $start, Carbon $end): array
    {
        $units = [];
        $cursor = $start->copy()->startOfQuarter();
        $last = $end->copy()->startOfQuarter();

        while ($cursor <= $last) {
            $quarter = (int) ceil($cursor->month / 3);

            $units[] = [
                'stage' => 'quarterly',
                'year' => (int) $cursor->year,
                'quarter' => $quarter,
                'label' => 'quarterly:' . $cursor->year . '-Q' . $quarter,
            ];

            $cursor->addQuarter();
        }

        return $units;
    }

    protected function buildYearlyUnits(Carbon $start, Carbon $end): array
    {
        $units = [];

        for ($year = (int) $start->year; $year <= (int) $end->year; $year++) {
            $units[] = [
                'stage' => 'yearly',
                'year' => $year,
                'label' => "yearly:{$year}",
            ];
        }

        return $units;
    }

    protected function runUnit(AggregationService $service, array $unit): void
    {
        match ($unit['stage']) {
            'hourly' => $service->updateHourlySummaries(Carbon::parse($unit['date']), $this->aggregationId),
            'daily' => $service->updateDailySummaries(Carbon::parse($unit['date']), $this->aggregationId),
            'weekly' => $service->updateWeeklySummariesRange(
                Carbon::parse($unit['start_date']),
                Carbon::parse($unit['end_date'])
            ),
            'monthly' => $service->updateMonthlySummariesYearMonth($unit['year'], $unit['month']),
            'quarterly' => $service->updateQuarterlySummariesYearQuarter($unit['year'], $unit['quarter']),
            'yearly' => $service->updateYearlySummariesYear($unit['year']),
            default => null,
        };
    }

    protected function updateProgress(string $status, array $data = []): void
    {
        $current = Cache::get("agg_progress_{$this->aggregationId}", []);

        $progress = array_merge($current, [
            'status' => $status,
            'aggregation_id' => $this->aggregationId,
            'type' => $this->type,
            'updated_at' => now()->toISOString(),
        ], $data);

        Cache::put("agg_progress_{$this->aggregationId}", $progress, 60 * 60 * 24);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Aggregation rebuild job crashed', [
            'aggregation_id' => $this->aggregationId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'type' => $this->type,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->updateProgress('failed', [
            'message' => $e->getMessage(),
            'current_unit' => null,
            'failed_at' => now()->toISOString(),
        ]);
    }
}