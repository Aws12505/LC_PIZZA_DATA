<?php

namespace App\Jobs\RebuildAggregationPipeline;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Continues the pipeline AFTER a stage batch finishes.
 * This exists to avoid calling pipeline methods from inside batch callbacks.
 */
class ContinueAggregationPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 3;

    public function __construct(
        public string $rebuildId,
        public string $startDate,
        public string $endDate,
        public string $type,
        public array  $stages,
        public int    $nextIndex
    ) {}

    public function handle(): void
    {
        RebuildAggregationPipelineJob::dispatch(
            rebuildId: $this->rebuildId,
            startDate: $this->startDate,
            endDate: $this->endDate,
            type: $this->type,
            stages: $this->stages,
            stageIndex: $this->nextIndex
        );
    }
}
