<?php

namespace App\Console\Commands\Aggregation;

use App\Jobs\RunAggregationRebuildJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RebuildAggregationsCommand extends Command
{
    protected $signature = 'aggregation:rebuild
                            {--start= : Start date (Y-m-d)}
                            {--end= : End date (Y-m-d)}
                            {--type=all : hourly, daily, weekly, monthly, quarterly, yearly, all}';

    protected $description = 'Queue a background aggregation rebuild';

    public function handle(): int
    {
        $this->newLine();
        $this->info('🔄 QUEUE AGGREGATION REBUILD');
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
            $end = Carbon::parse($this->option('end'))->startOfDay();
        } catch (\Throwable $e) {
            $this->error('❌ Invalid date format (use Y-m-d)');
            return self::FAILURE;
        }

        if ($start->gt($end)) {
            $this->error('❌ Start date must be <= end date');
            return self::FAILURE;
        }

        $this->table(['Start', 'End', 'Type'], [
            [
                $start->toDateString(),
                $end->toDateString(),
                $type,
            ]
        ]);

        if (!$this->confirm('🚀 Queue rebuild?', true)) {
            return self::SUCCESS;
        }

        $aggregationId = uniqid('agg_', true);

        Cache::put("agg_progress_{$aggregationId}", [
            'status' => 'queued',
            'aggregation_id' => $aggregationId,
            'type' => $type,
            'processed' => 0,
            'total' => 0,
            'successful' => 0,
            'failed' => 0,
            'started_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'current_unit' => null,
            'failed_units' => [],
        ], 60 * 60 * 24);

        RunAggregationRebuildJob::dispatch(
            $aggregationId,
            $start->toDateString(),
            $end->toDateString(),
            $type
        );

        $this->newLine();
        $this->info("✅ Rebuild queued. ID: {$aggregationId}");
        $this->info("💡 Progress key: agg_progress_{$aggregationId}");
        $this->info("💡 Run worker: php artisan queue:work --queue=default --timeout=21600");

        return self::SUCCESS;
    }
}