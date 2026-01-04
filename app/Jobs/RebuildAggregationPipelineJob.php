<?php

namespace App\Jobs;

use App\Services\Aggregation\AggregationService;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Jobs\RebuildAggregationPipeline\{RebuildHourlyDayJob, RebuildDailyDayJob, RebuildWeeklyRangeJob, RebuildMonthlyJob, RebuildQuarterlyJob, RebuildYearlyJob};
class RebuildAggregationPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $timeout = 3600;
    public $tries = 3;

    public function __construct(
        protected string $rebuildId,
        protected string $startDate,
        protected string $endDate,
        protected string $type = 'all' // hourly|daily|weekly|monthly|quarterly|yearly|all
    ) {}

    public function handle(): void
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end   = Carbon::parse($this->endDate)->startOfDay();

        // Normalize to dependency-safe stages
        $stages = $this->stagesForType($this->type);

        Cache::put("agg_rebuild_progress_{$this->rebuildId}", [
            'status' => 'queued',
            'rebuild_id' => $this->rebuildId,
            'type' => $this->type,
            'stages' => $stages,
            'current_stage' => null,
            'started_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
            'date_range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
        ], 7200);

        Log::info('Aggregation rebuild pipeline starting', [
            'rebuild_id' => $this->rebuildId,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'type' => $this->type,
            'stages' => $stages,
        ]);

        // Kick off pipeline from first stage
        $this->dispatchStage($stages, 0, $start, $end);
    }

    /**
     * Stage orchestration using chained batches.
     */
    protected function dispatchStage(array $stages, int $index, Carbon $start, Carbon $end): void
    {
        if ($index >= count($stages)) {
            $this->updateProgress([
                'status' => 'completed',
                'current_stage' => null,
            ]);

            Log::info('Aggregation rebuild pipeline completed', ['rebuild_id' => $this->rebuildId]);
            return;
        }

        $stage = $stages[$index];

        $jobs = match ($stage) {
            'hourly'    => $this->makeHourlyJobs($start, $end),
            'daily'     => $this->makeDailyJobs($start, $end),
            'weekly'    => [new RebuildWeeklyRangeJob($this->rebuildId, $start->toDateString(), $end->toDateString())],
            'monthly'   => $this->makeMonthlyJobs($start, $end),
            'quarterly' => $this->makeQuarterlyJobs($start, $end),
            'yearly'    => $this->makeYearlyJobs($start, $end),
            default     => [],
        };

        if (empty($jobs)) {
            // Nothing to do for this stage → continue
            $this->dispatchStage($stages, $index + 1, $start, $end);
            return;
        }

        $this->updateProgress([
            'status' => 'processing',
            'current_stage' => $stage,
            'stage_index' => $index,
            'stage_total' => count($stages),
            'stage_job_count' => count($jobs),
        ]);

        Bus::batch($jobs)
            ->name("agg_rebuild:{$this->rebuildId}:{$stage}")
            ->onQueue('default')
            ->allowFailures()
            ->then(function (Batch $batch) use ($stages, $index, $start, $end, $stage) {
                Log::info('Aggregation stage completed', [
                    'rebuild_id' => $this->rebuildId,
                    'stage' => $stage,
                    'batch_id' => $batch->id,
                    'total_jobs' => $batch->totalJobs,
                    'failed_jobs' => $batch->failedJobs,
                ]);

                $this->updateProgress([
                    'status' => 'processing',
                    'current_stage' => $stage,
                    'last_stage_completed' => $stage,
                    'last_batch_id' => $batch->id,
                    'last_failed_jobs' => $batch->failedJobs,
                ]);

                // Next stage
                $this->dispatchStage($stages, $index + 1, $start, $end);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($stage) {
                Log::error('Aggregation stage batch failed', [
                    'rebuild_id' => $this->rebuildId,
                    'stage' => $stage,
                    'batch_id' => $batch->id ?? null,
                    'error' => $e->getMessage(),
                ]);

                $this->updateProgress([
                    'status' => 'failed',
                    'current_stage' => $stage,
                    'error' => $e->getMessage(),
                ]);
            })
            ->dispatch();
    }

    protected function stagesForType(string $type): array
    {
        // “type” means “run up to this level”, respecting dependencies
        return match ($type) {
            'hourly'    => ['hourly'],
            'daily'     => ['hourly', 'daily'],
            'weekly'    => ['hourly', 'daily', 'weekly'],
            'monthly'   => ['hourly', 'daily', 'weekly', 'monthly'],
            'quarterly' => ['hourly', 'daily', 'weekly', 'monthly', 'quarterly'],
            'yearly'    => ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'],
            'all'       => ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'],
            default     => ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'],
        };
    }

    protected function makeHourlyJobs(Carbon $start, Carbon $end): array
    {
        $jobs = [];
        $d = $start->copy();
        while ($d <= $end) {
            $jobs[] = new RebuildHourlyDayJob($this->rebuildId, $d->toDateString());
            $d->addDay();
        }
        return $jobs;
    }

    protected function makeDailyJobs(Carbon $start, Carbon $end): array
    {
        $jobs = [];
        $d = $start->copy();
        while ($d <= $end) {
            $jobs[] = new RebuildDailyDayJob($this->rebuildId, $d->toDateString());
            $d->addDay();
        }
        return $jobs;
    }

    protected function makeMonthlyJobs(Carbon $start, Carbon $end): array
    {
        $jobs = [];
        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();

        while ($cursor <= $last) {
            $jobs[] = new RebuildMonthlyJob($this->rebuildId, (int) $cursor->year, (int) $cursor->month);
            $cursor->addMonth();
        }

        return $jobs;
    }

    protected function makeQuarterlyJobs(Carbon $start, Carbon $end): array
    {
        $jobs = [];
        $cursor = $start->copy()->startOfQuarter();
        $last = $end->copy()->startOfQuarter();

        while ($cursor <= $last) {
            $q = (int) ceil($cursor->month / 3);
            $jobs[] = new RebuildQuarterlyJob($this->rebuildId, (int) $cursor->year, $q);
            $cursor->addQuarter();
        }

        return $jobs;
    }

    protected function makeYearlyJobs(Carbon $start, Carbon $end): array
    {
        $jobs = [];
        for ($y = (int) $start->year; $y <= (int) $end->year; $y++) {
            $jobs[] = new RebuildYearlyJob($this->rebuildId, $y);
        }
        return $jobs;
    }

    protected function updateProgress(array $patch): void
    {
        $key = "agg_rebuild_progress_{$this->rebuildId}";
        $cur = Cache::get($key, []);
        $cur['updated_at'] = now()->toISOString();

        foreach ($patch as $k => $v) {
            $cur[$k] = $v;
        }

        Cache::put($key, $cur, 7200);
    }
}
