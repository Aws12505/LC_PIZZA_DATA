<?php

namespace App\Jobs\RebuildAggregationPipeline;

use App\Services\Aggregation\AggregationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RebuildQuarterlyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 3;

    public function __construct(
        protected string $rebuildId,
        protected int $year,
        protected int $quarter
    ) {}

    public function handle(AggregationService $service): void
    {
        Log::info('Rebuild quarterly', [
            'rebuild_id' => $this->rebuildId,
            'year' => $this->year,
            'quarter' => $this->quarter,
        ]);

        $service->updateQuarterlySummariesYearQuarter($this->year, $this->quarter);
    }
}
