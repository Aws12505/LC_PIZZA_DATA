<?php

namespace App\Console\Commands\Aggregation;

use App\Jobs\RebuildAggregationPipeline\RebuildAggregationPipelineJob;
use App\Models\Aggregation\DailyStoreSummary;
use App\Models\Aggregation\HourlyStoreSummary;
use App\Models\Aggregation\MonthlyStoreSummary;
use App\Models\Aggregation\QuarterlyStoreSummary;
use App\Models\Aggregation\WeeklyStoreSummary;
use App\Services\Database\DatabaseRouter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RebuildAggregationsCommand extends Command
{
    protected $signature = 'aggregation:rebuild
                            {--start= : Start date (Y-m-d)}
                            {--end= : End date (Y-m-d)}
                            {--type=all : hourly, daily, weekly, monthly, quarterly, yearly, all}
                            {--without= : Comma-separated stages to skip (hourly,daily,weekly,monthly,quarterly,yearly)}';

    protected $description = 'Rebuild aggregations for a date range (JOB PIPELINE: hourly->daily->weekly->monthly->quarterly->yearly)';

    public function handle(): int
    {
        $this->newLine();
        $this->info('🔄 REBUILD AGGREGATIONS (JOB PIPELINE)');
        $this->line(str_repeat('═', 80));

        if (!$this->option('start') || !$this->option('end')) {
            $this->error('❌ Both --start and --end dates required');
            return self::FAILURE;
        }

        $type = (string) $this->option('type');
        $valid = ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'all'];
        if (!in_array($type, $valid, true)) {
            $this->error('❌ Invalid --type. Must be: ' . implode(', ', $valid));
            return self::FAILURE;
        }

        $withoutRaw = (string) ($this->option('without') ?? '');
        $without = $this->parseWithoutStages($withoutRaw);
        $invalidWithout = array_values(array_diff($without, ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly']));
        if (!empty($invalidWithout)) {
            $this->error('❌ Invalid --without values: ' . implode(', ', $invalidWithout));
            return self::FAILURE;
        }

        $stages = $this->buildStages($type, $without);
        if (empty($stages)) {
            $this->error('❌ No stages remain after exclusions. Adjust --type/--without.');
            return self::FAILURE;
        }

        try {
            $start = Carbon::parse($this->option('start'))->startOfDay();
            $end = Carbon::parse($this->option('end'))->startOfDay();
        } catch (\Exception $e) {
            $this->error('❌ Invalid date format (use Y-m-d)');
            return self::FAILURE;
        }

        if ($start->gt($end)) {
            $this->error('❌ Start date must be <= end date');
            return self::FAILURE;
        }

        $days = $start->diffInDays($end) + 1;

        $this->table(['Start', 'End', 'Days', 'Type', 'Without', 'Stages'], [
            [
                $start->toDateString(),
                $end->toDateString(),
                $days,
                $type,
                empty($without) ? '-' : implode(',', $without),
                implode(' -> ', $stages),
            ]
        ]);

        $coverage = $this->buildCoverageReport($start, $end, $stages);
        $this->renderCoverageReport($coverage);

        if (!$this->confirm('🚀 Queue rebuild pipeline?', true)) {
            return self::SUCCESS;
        }

        $rebuildId = uniqid('agg_rebuild_', true);

        Cache::put("agg_rebuild_progress_{$rebuildId}", [
            'status' => 'queued',
            'rebuild_id' => $rebuildId,
            'type' => $type,
            'without' => $without,
            'stages' => $stages,
            'coverage' => $coverage,
            'started_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
            'date_range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
        ], 7200);

        RebuildAggregationPipelineJob::dispatch(
            rebuildId: $rebuildId,
            startDate: $start->toDateString(),
            endDate: $end->toDateString(),
            type: $type,
            stages: $stages
        );

        $this->newLine();
        $this->info("✅ Rebuild queued. ID: {$rebuildId}");
        $this->info("💡 Run worker: php artisan queue:work");
        $this->info("💡 Progress key: agg_rebuild_progress_{$rebuildId}");

        return self::SUCCESS;
    }

    private function parseWithoutStages(string $withoutRaw): array
    {
        if (trim($withoutRaw) === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', strtolower($withoutRaw)));
        $parts = array_values(array_filter($parts, static fn($s) => $s !== ''));

        return array_values(array_unique($parts));
    }

    private function buildStages(string $type, array $without): array
    {
        $base = match ($type) {
            'hourly' => ['hourly'],
            'daily' => ['hourly', 'daily'],
            'weekly' => ['hourly', 'daily', 'weekly'],
            'monthly' => ['hourly', 'daily', 'weekly', 'monthly'],
            'quarterly' => ['hourly', 'daily', 'weekly', 'monthly', 'quarterly'],
            'yearly' => ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'],
            'all' => ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'],
            default => ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'],
        };

        return array_values(array_filter(
            $base,
            static fn($stage) => !in_array($stage, $without, true)
        ));
    }

    private function buildCoverageReport(Carbon $start, Carbon $end, array $selectedStages): array
    {
        $fullChain = ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'];
        $report = [];
        $pipelineDatePool = $this->pipelineDatePool($start, $end, $selectedStages);

        foreach ($fullChain as $stage) {
            $currentUnits = $this->availableUnitsForStage($stage, $start, $end);
            $projectedUnits = in_array($stage, $selectedStages, true)
                ? $this->projectedUnitsForStage($stage, $pipelineDatePool)
                : $currentUnits;
            $expectedUnits = $this->expectedUnitsForStage($stage, $start, $end);
            $missingUnits = array_values(array_diff($expectedUnits, $projectedUnits));

            $report[] = [
                'stage' => $stage,
                'selected' => in_array($stage, $selectedStages, true),
                'current_units' => count($currentUnits),
                'projected_units' => count($projectedUnits),
                'expected_units' => count($expectedUnits),
                'missing_units' => count($missingUnits),
                'current_labels' => $currentUnits,
                'projected_labels' => $projectedUnits,
                'missing_labels' => $missingUnits,
                'missing_reason' => $this->missingReasonForStage($stage),
            ];
        }

        return $report;
    }

    private function renderCoverageReport(array $coverage): void
    {
        $this->newLine();
        $this->line('Coverage preflight:');

        foreach ($coverage as $stage) {
            $status = $stage['selected'] ? 'selected' : 'skipped';
            $this->line(sprintf(
                '  - %s: current %d/%d, projected %d/%d, missing %d [%s]',
                $stage['stage'],
                $stage['current_units'],
                $stage['expected_units'],
                $stage['projected_units'],
                $stage['expected_units'],
                $stage['missing_units'],
                $status
            ));

            foreach ($stage['missing_labels'] as $label) {
                Log::warning('Aggregation preflight missing unit', [
                    'stage' => $stage['stage'],
                    'unit' => $label,
                    'reason' => $stage['missing_reason'],
                ]);
            }
        }

        $this->newLine();
    }

    private function availableUnitsForStage(string $stage, Carbon $start, Carbon $end): array
    {
        return match ($stage) {
            'hourly' => $this->uniqueValues($this->routedSource('detail_orders', $start, $end)->select('business_date')->pluck('business_date')->all()),
            'daily' => $this->uniqueValues(HourlyStoreSummary::query()
                ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
                ->pluck('business_date')
                ->all()),
            'weekly' => $this->uniqueValues(DailyStoreSummary::query()
                ->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
                ->pluck('business_date')
                ->map(fn($date) => $this->weekLabel(Carbon::parse($date)))
                ->all()),
            'monthly' => $this->uniqueValues($this->monthLabelsFromWeeklyRows($start, $end)),
            'quarterly' => $this->uniqueValues($this->quarterLabelsFromMonthlyRows($start, $end)),
            'yearly' => $this->uniqueValues(QuarterlyStoreSummary::query()
                ->whereBetween('year_num', [$start->year, $end->year])
                ->pluck('year_num')
                ->map(fn($year) => (string) $year)
                ->all()),
            default => [],
        };
    }

    private function pipelineDatePool(Carbon $start, Carbon $end, array $selectedStages): array
    {
        if (in_array('hourly', $selectedStages, true)) {
            return $this->availableUnitsForStage('hourly', $start, $end);
        }

        if (in_array('daily', $selectedStages, true)) {
            return $this->availableUnitsForStage('daily', $start, $end);
        }

        if (in_array('weekly', $selectedStages, true)) {
            return $this->datePoolFromWeeklyStage($start, $end);
        }

        if (in_array('monthly', $selectedStages, true)) {
            return $this->datePoolFromMonthlyStage($start, $end);
        }

        if (in_array('quarterly', $selectedStages, true)) {
            return $this->datePoolFromQuarterlyStage($start, $end);
        }

        if (in_array('yearly', $selectedStages, true)) {
            return $this->datePoolFromYearlyStage($start, $end);
        }

        return $this->availableUnitsForStage('hourly', $start, $end);
    }

    private function projectedUnitsForStage(string $stage, array $datePool): array
    {
        return match ($stage) {
            'hourly', 'daily' => $this->uniqueValues($datePool),
            'weekly' => $this->uniqueValues(array_map(fn($date) => $this->weekLabel(Carbon::parse($date)), $datePool)),
            'monthly' => $this->uniqueValues(array_map(fn($date) => $this->monthLabel(Carbon::parse($date)), $datePool)),
            'quarterly' => $this->uniqueValues(array_map(fn($date) => $this->quarterLabel(Carbon::parse($date)), $datePool)),
            'yearly' => $this->uniqueValues(array_map(fn($date) => (string) Carbon::parse($date)->year, $datePool)),
            default => [],
        };
    }

    private function datePoolFromWeeklyStage(Carbon $start, Carbon $end): array
    {
        $rows = WeeklyStoreSummary::query()
            ->whereBetween('week_start_date', [$start->toDateString(), $end->toDateString()])
            ->orWhereBetween('week_end_date', [$start->toDateString(), $end->toDateString()])
            ->get(['week_start_date', 'week_end_date']);

        $dates = [];
        foreach ($rows as $row) {
            if ($row->week_start_date) {
                $dates[] = $row->week_start_date;
            }
            if ($row->week_end_date) {
                $dates[] = $row->week_end_date;
            }
        }

        return $this->uniqueValues($dates);
    }

    private function datePoolFromMonthlyStage(Carbon $start, Carbon $end): array
    {
        return $this->uniqueValues(MonthlyStoreSummary::query()
            ->where('year_num', '>=', $start->year)
            ->where('year_num', '<=', $end->year)
            ->get(['year_num', 'month_num'])
            ->map(fn($row) => Carbon::create((int) $row->year_num, (int) $row->month_num, 1)->toDateString())
            ->all());
    }

    private function datePoolFromQuarterlyStage(Carbon $start, Carbon $end): array
    {
        return $this->uniqueValues(QuarterlyStoreSummary::query()
            ->where('year_num', '>=', $start->year)
            ->where('year_num', '<=', $end->year)
            ->get(['year_num', 'quarter_num'])
            ->map(fn($row) => Carbon::create((int) $row->year_num, ((int) $row->quarter_num - 1) * 3 + 1, 1)->toDateString())
            ->all());
    }

    private function datePoolFromYearlyStage(Carbon $start, Carbon $end): array
    {
        return $this->uniqueValues(QuarterlyStoreSummary::query()
            ->where('year_num', '>=', $start->year)
            ->where('year_num', '<=', $end->year)
            ->pluck('year_num')
            ->map(fn($year) => Carbon::create((int) $year, 1, 1)->toDateString())
            ->all());
    }

    private function expectedUnitsForStage(string $stage, Carbon $start, Carbon $end): array
    {
        $units = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor <= $last) {
            $units[] = match ($stage) {
                'hourly', 'daily' => $cursor->toDateString(),
                'weekly' => $this->weekLabel($cursor),
                'monthly' => $this->monthLabel($cursor),
                'quarterly' => $this->quarterLabel($cursor),
                'yearly' => (string) $cursor->year,
                default => $cursor->toDateString(),
            };

            $cursor->addDay();
        }

        return $this->uniqueValues($units);
    }

    private function routedSource(string $baseTable, Carbon $start, Carbon $end): Builder
    {
        $queries = DatabaseRouter::routedQueries($baseTable, $start, $end);

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, 'src');
    }

    private function monthLabelsFromWeeklyRows(Carbon $start, Carbon $end): array
    {
        $rows = WeeklyStoreSummary::query()
            ->where('year_num', '>=', $start->year)
            ->where('year_num', '<=', $end->year)
            ->get(['week_start_date', 'week_end_date']);

        $labels = [];

        foreach ($rows as $row) {
            if ($row->week_start_date) {
                $labels[] = $this->monthLabel(Carbon::parse($row->week_start_date));
            }

            if ($row->week_end_date) {
                $labels[] = $this->monthLabel(Carbon::parse($row->week_end_date));
            }
        }

        return $labels;
    }

    private function quarterLabelsFromMonthlyRows(Carbon $start, Carbon $end): array
    {
        $rows = MonthlyStoreSummary::query()
            ->where('year_num', '>=', $start->year)
            ->where('year_num', '<=', $end->year)
            ->get(['year_num', 'month_num']);

        return $rows->map(fn($row) => sprintf('%d-Q%d', (int) $row->year_num, (int) ceil(((int) $row->month_num) / 3)))->all();
    }

    private function weekLabel(Carbon $date): string
    {
        return sprintf('%d-W%02d', $date->year, (int) $date->format('W'));
    }

    private function monthLabel(Carbon $date): string
    {
        return $date->format('Y-m');
    }

    private function quarterLabel(Carbon $date): string
    {
        return sprintf('%d-Q%d', $date->year, (int) ceil($date->month / 3));
    }

    private function missingReasonForStage(string $stage): string
    {
        return match ($stage) {
            'hourly' => 'No raw detail_orders rows for that day.',
            'daily' => 'No hourly summaries for that day.',
            'weekly' => 'No daily summaries for that week bucket.',
            'monthly' => 'No weekly summaries for that month bucket.',
            'quarterly' => 'No monthly summaries for that quarter bucket.',
            'yearly' => 'No quarterly summaries for that year bucket.',
            default => 'No source data available.',
        };
    }

    private function uniqueValues(array $values): array
    {
        $values = array_map(static fn($value) => (string) $value, $values);
        $values = array_values(array_filter($values, static fn($value) => $value !== ''));

        return array_values(array_unique($values));
    }
}
