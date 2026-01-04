<?php

namespace App\Jobs\RebuildAggregationPipeline;

use App\Services\Aggregation\AggregationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RebuildWeeklyRangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 3;

    public function __construct(
        protected string $rebuildId,
        protected string $startDate,
        protected string $endDate
    ) {}

    public function handle(AggregationService $service): void
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end   = Carbon::parse($this->endDate)->startOfDay();

        Log::info('Rebuild weekly range', [
            'rebuild_id' => $this->rebuildId,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ]);

        // Your service already supports weekly range aggregation from DAILY:
        $service->updateWeeklySummariesRange($start, $end);
    }
}
