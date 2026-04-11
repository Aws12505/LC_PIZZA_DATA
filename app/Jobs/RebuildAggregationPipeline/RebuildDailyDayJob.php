<?php

namespace App\Jobs\RebuildAggregationPipeline;

use App\Services\Aggregation\AggregationService;
use App\Support\AggregationRebuildLogger;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Throwable;

class RebuildDailyDayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $timeout = 1800;
    public $tries = 3;

    public function __construct(
        protected string $rebuildId,
        protected string $businessDate
    ) {
    }

    public function handle(AggregationService $service): void
    {
        $date = Carbon::parse($this->businessDate);

        AggregationRebuildLogger::info('Daily rebuild day job started', [
            'rebuild_id' => $this->rebuildId,
            'business_date' => $date->toDateString(),
            'job' => static::class,
            'attempt' => method_exists($this, 'attempts') ? $this->attempts() : null,
        ]);

        try {
            $service->updateDailySummaries($date, $this->rebuildId);

            AggregationRebuildLogger::info('Daily rebuild day job completed', [
                'rebuild_id' => $this->rebuildId,
                'business_date' => $date->toDateString(),
                'job' => static::class,
            ]);
        } catch (Throwable $e) {
            AggregationRebuildLogger::error('Daily rebuild day job failed', [
                'rebuild_id' => $this->rebuildId,
                'business_date' => $date->toDateString(),
                'job' => static::class,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        AggregationRebuildLogger::critical('Daily rebuild day job permanently failed', [
            'rebuild_id' => $this->rebuildId,
            'business_date' => $this->businessDate,
            'job' => static::class,
            'exception' => $e,
        ]);
    }
}