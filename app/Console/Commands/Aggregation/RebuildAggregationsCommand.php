<?php

namespace App\Console\Commands\Aggregation;

use App\Services\Aggregation\AggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rebuild aggregations for a date range
 * Usage:
 *   php artisan aggregation:rebuild --start=2025-01-01 --end=2025-01-31 --type=all
 *   php artisan aggregation:rebuild --start=2025-01-01 --end=2025-01-31 --type=hourly
 *   php artisan aggregation:rebuild --start=2025-01-01 --end=2025-01-31 --type=daily
 */
class RebuildAggregationsCommand extends Command
{
    protected $signature = 'aggregation:rebuild
                            {--start= : Start date (Y-m-d)}
                            {--end= : End date (Y-m-d)}
                            {--type=all : Type: hourly, daily, weekly, monthly, quarterly, yearly, all}';

    protected $description = 'Rebuild aggregations for a date range';

    public function __construct(
        protected AggregationService $aggregationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->newLine();
        $this->info('🔄 REBUILD AGGREGATIONS');
        $this->line(str_repeat('═', 80));

        if (!$this->option('start') || !$this->option('end')) {
            $this->error('❌ Both --start and --end dates required');
            return self::FAILURE;
        }

        try {
            $start = Carbon::parse($this->option('start'));
            $end = Carbon::parse($this->option('end'));
        } catch (\Exception $e) {
            $this->error('❌ Invalid date format (use Y-m-d)');
            return self::FAILURE;
        }

        $days = $start->diffInDays($end) + 1;
        $type = $this->option('type');

        $this->table(['Start', 'End', 'Days', 'Type'], [
            [$start->toDateString(), $end->toDateString(), $days, $type]
        ]);

        if (!$this->confirm('🚀 Proceed with rebuild?', true)) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($days);
        $bar->start();

        $current = $start->copy();
        $success = $failed = 0;

        while ($current <= $end) {
            try {
                // HOURLY must come first (builds from raw data)
                if ($type === 'hourly' || $type === 'all') {
                    $this->aggregationService->updateHourlySummaries($current);
                }

                // DAILY (builds from hourly)
                if ($type === 'daily' || $type === 'all') {
                    $this->aggregationService->updateDailySummaries($current);
                }

                // WEEKLY (builds from daily)
                if ($type === 'weekly' || $type === 'all') {
                    $this->aggregationService->updateWeeklySummaries($current);
                }

                // MONTHLY (builds from weekly)
                if ($type === 'monthly' || $type === 'all') {
                    $this->aggregationService->updateMonthlySummaries($current);
                }

                // QUARTERLY (builds from monthly)
                if ($type === 'quarterly' || $type === 'all') {
                    $this->aggregationService->updateQuarterlySummaries($current);
                }

                // YEARLY (builds from quarterly)
                if ($type === 'yearly' || $type === 'all') {
                    $this->aggregationService->updateYearlySummaries($current);
                }

                $success++;
            } catch (\Exception $e) {
                $failed++;
                Log::error("Rebuild {$current->toDateString()}: " . $e->getMessage());
            }

            $bar->advance();
            $current->addDay();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Success: {$success} | ❌ Failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
