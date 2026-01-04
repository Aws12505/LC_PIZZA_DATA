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

class RebuildHourlyDayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;
    public $tries = 3;

    public function __construct(
        protected string $rebuildId,
        protected string $businessDate
    ) {}

    public function handle(AggregationService $service): void
    {
        $date = Carbon::parse($this->businessDate);
        Log::info('Rebuild hourly', ['rebuild_id' => $this->rebuildId, 'date' => $date->toDateString()]);
        $service->updateHourlySummaries($date);
    }
}
