<?php

namespace App\Console\Commands\Aggregation;

use App\Jobs\RebuildAggregationPipelineJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RebuildAggregationsCommand extends Command
{
    protected $signature = 'aggregation:rebuild
                            {--start= : Start date (Y-m-d)}
                            {--end= : End date (Y-m-d)}
                            {--type=all : hourly, daily, weekly, monthly, quarterly, yearly, all}';

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

        try {
            $start = Carbon::parse($this->option('start'))->startOfDay();
            $end   = Carbon::parse($this->option('end'))->startOfDay();
        } catch (\Exception $e) {
            $this->error('❌ Invalid date format (use Y-m-d)');
            return self::FAILURE;
        }

        if ($start->gt($end)) {
            $this->error('❌ Start date must be <= end date');
            return self::FAILURE;
        }

        $days = $start->diffInDays($end) + 1;

        $this->table(['Start', 'End', 'Days', 'Type'], [
            [$start->toDateString(), $end->toDateString(), $days, $type]
        ]);

        if (!$this->confirm('🚀 Queue rebuild pipeline?', true)) {
            return self::SUCCESS;
        }

        $rebuildId = uniqid('agg_rebuild_', true);

        Cache::put("agg_rebuild_progress_{$rebuildId}", [
            'status' => 'queued',
            'rebuild_id' => $rebuildId,
            'type' => $type,
            'started_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
            'date_range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
        ], 7200);

        RebuildAggregationPipelineJob::dispatch(
            $rebuildId,
            $start->toDateString(),
            $end->toDateString(),
            $type
        );

        Log::info('Aggregation rebuild pipeline queued', [
            'rebuild_id' => $rebuildId,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'type' => $type,
        ]);

        $this->newLine();
        $this->info("✅ Rebuild queued. ID: {$rebuildId}");
        $this->info("💡 Run worker: php artisan queue:work");
        $this->info("💡 Progress key: agg_rebuild_progress_{$rebuildId}");

        return self::SUCCESS;
    }
}
