<?php

namespace App\Console\Commands\Aggregation;

use App\Jobs\RebuildAggregationPipeline\RebuildAggregationPipelineJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RebuildAggregationsCommand extends Command
{
    protected $signature = 'aggregation:rebuild
                            {--start= : Start date (Y-m-d)}
                            {--end= : End date (Y-m-d)}
                            {--type=all : hourly, daily, weekly, monthly, quarterly, yearly, all}
                            {--without= : Comma-separated stages to skip (hourly,daily,weekly,monthly,quarterly,yearly)}
                            {--only= : Comma-separated stages to run only (hourly,daily,weekly,monthly,quarterly,yearly)}';

    protected $description = 'Rebuild aggregations for a date range (JOB PIPELINE: hourly->daily->weekly->monthly->quarterly->yearly)';

    public function handle(): int
    {
        $this->newLine();
        $this->info('REBUILD AGGREGATIONS (JOB PIPELINE)');
        $this->line(str_repeat('=', 80));

        if (!$this->option('start') || !$this->option('end')) {
            $this->error('Both --start and --end dates required');
            return self::FAILURE;
        }

        $type = (string) $this->option('type');
        $validTypes = ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'all'];
        if (!in_array($type, $validTypes, true)) {
            $this->error('Invalid --type. Must be: ' . implode(', ', $validTypes));
            return self::FAILURE;
        }

        $without = $this->parseStagesList((string) ($this->option('without') ?? ''));
        $only = $this->parseStagesList((string) ($this->option('only') ?? ''));

        $validStages = ['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'];

        $invalidWithout = array_values(array_diff($without, $validStages));
        if (!empty($invalidWithout)) {
            $this->error('Invalid --without values: ' . implode(', ', $invalidWithout));
            return self::FAILURE;
        }

        $invalidOnly = array_values(array_diff($only, $validStages));
        if (!empty($invalidOnly)) {
            $this->error('Invalid --only values: ' . implode(', ', $invalidOnly));
            return self::FAILURE;
        }

        if (!empty($only) && !empty($without)) {
            $this->error('Use either --only or --without, not both in the same command.');
            return self::FAILURE;
        }

        $stages = $this->buildStages($type, $without, $only);
        if (empty($stages)) {
            $this->error('No stages selected. Adjust --type/--without/--only.');
            return self::FAILURE;
        }

        try {
            $start = Carbon::parse($this->option('start'))->startOfDay();
            $end = Carbon::parse($this->option('end'))->startOfDay();
        } catch (\Exception $e) {
            $this->error('Invalid date format (use Y-m-d)');
            return self::FAILURE;
        }

        if ($start->gt($end)) {
            $this->error('Start date must be <= end date');
            return self::FAILURE;
        }

        $days = $start->diffInDays($end) + 1;

        $this->table(['Start', 'End', 'Days', 'Type', 'Without', 'Only', 'Stages'], [
            [
                $start->toDateString(),
                $end->toDateString(),
                $days,
                $type,
                empty($without) ? '-' : implode(',', $without),
                empty($only) ? '-' : implode(',', $only),
                implode(' -> ', $stages),
            ]
        ]);

        if (!$this->confirm('Queue rebuild pipeline?', true)) {
            return self::SUCCESS;
        }

        $rebuildId = uniqid('agg_rebuild_', true);

        Cache::put("agg_rebuild_progress_{$rebuildId}", [
            'status' => 'queued',
            'rebuild_id' => $rebuildId,
            'type' => $type,
            'without' => $without,
            'only' => $only,
            'stages' => $stages,
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
        $this->info("Rebuild queued. ID: {$rebuildId}");
        $this->info("Run worker: php artisan queue:work");
        $this->info("Progress key: agg_rebuild_progress_{$rebuildId}");

        return self::SUCCESS;
    }

    private function parseStagesList(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', strtolower($raw)));
        $parts = array_values(array_filter($parts, static fn($s) => $s !== ''));

        return array_values(array_unique($parts));
    }

    private function buildStages(string $type, array $without, array $only): array
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

        if (!empty($only)) {
            return array_values(array_filter(
                $base,
                static fn($stage) => in_array($stage, $only, true)
            ));
        }

        return array_values(array_filter(
            $base,
            static fn($stage) => !in_array($stage, $without, true)
        ));
    }
}
