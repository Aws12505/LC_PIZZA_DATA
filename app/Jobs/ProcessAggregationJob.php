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

class ProcessAggregationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 3;

    protected string $aggregationId;
    protected string $startDate;
    protected string $endDate;
    protected string $type;

    public function __construct(string $aggregationId, string $startDate, string $endDate, string $type)
    {
        $this->aggregationId = $aggregationId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->type = $type;
    }

    public function handle(AggregationService $service)
    {
        ini_set('memory_limit', '2G');
        
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);
        $total = $start->diffInDays($end) + 1;
        

        $processed = 0;
        $successful = 0;
        $failed = 0;

        $current = $start->copy();
        while ($current <= $end) {
            try {
                $this->updateProgress('processing', [
                    'total' => $total,
                    'processed' => $processed,
                    'current_date' => $current->toDateString()
                ]);

                // Use YOUR existing AggregationService methods
                match ($this->type) {
                    'hourly' => $service->updateHourlySummaries($current),
                    'daily' => $service->updateDailySummaries($current),
                    'weekly' => $service->updateWeeklySummaries($current),
                    'monthly' => $service->updateMonthlySummaries($current),
                    'quarterly' => $service->updateQuarterlySummaries($current),
                    'yearly' => $service->updateYearlySummaries($current),
                    'all' => $this->aggregateAll($service, $current),
                    default => null
                };

                $successful++;

            } catch (\Exception $e) {
                $failed++;
                Log::error("Aggregation failed for date", [
                    'date' => $current->toDateString(),
                    'error' => $e->getMessage()
                ]);
            }

            $processed++;
            $current->addDay();
        }

        $this->updateProgress('completed', [
            'total' => $total,
            'processed' => $processed,
            'successful' => $successful,
            'failed' => $failed
        ]);

    }

    protected function aggregateAll(AggregationService $service, Carbon $date): void
    {
        $service->updateHourlySummaries($date);
        $service->updateDailySummaries($date);
        $service->updateWeeklySummaries($date);
        $service->updateMonthlySummaries($date);
        $service->updateQuarterlySummaries($date);
        $service->updateYearlySummaries($date);
    }

    protected function updateProgress(string $status, array $data = [])
    {
        $progress = [
            'status' => $status,
            'type' => $this->type,
            'updated_at' => now()->toISOString()
        ];

        foreach ($data as $key => $value) {
            $progress[$key] = $value;
        }

        Cache::put("agg_progress_{$this->aggregationId}", $progress, 3600);
    }
}
