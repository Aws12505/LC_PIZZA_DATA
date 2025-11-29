<?php

namespace App\Console\Commands\Aggregation;

use App\Services\Aggregation\AggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Rebuild aggregations for a date range
 * 
 * Usage:
 *   php artisan aggregation:rebuild --start=2025-01-01 --end=2025-01-31
 *   php artisan aggregation:rebuild --start=2025-01-01 --end=2025-01-31 --type=daily
 */
class RebuildAggregationsCommand extends Command
{
    protected $signature = 'aggregation:rebuild 
                            {--start= : Start date (Y-m-d format)}
                            {--end= : End date (Y-m-d format)}
                            {--type=daily : Type to rebuild (daily, weekly, monthly, all)}';

    protected $description = 'Rebuild aggregations for a date range';

    protected AggregationService $aggregationService;

    public function __construct(AggregationService $aggregationService)
    {
        parent::__construct();
        $this->aggregationService = $aggregationService;
    }

    public function handle(): int
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  Rebuild Aggregations');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if (!$this->option('start') || !$this->option('end')) {
            $this->error('Both --start and --end dates are required');
            return self::FAILURE;
        }

        try {
            $startDate = Carbon::parse($this->option('start'));
            $endDate = Carbon::parse($this->option('end'));
        } catch (\Exception $e) {
            $this->error('Invalid date format');
            return self::FAILURE;
        }

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $type = $this->option('type');

        $this->info("📅 Start: {$startDate->toDateString()}");
        $this->info("📅 End: {$endDate->toDateString()}");
        $this->info("📊 Days: {$totalDays}");
        $this->info("📊 Type: {$type}");
        $this->newLine();

        if (!$this->confirm('This will rebuild aggregations. Continue?', true)) {
            return self::SUCCESS;
        }

        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalDays);
        $progressBar->start();

        $currentDate = $startDate->copy();
        $successful = 0;
        $failed = 0;

        while ($currentDate <= $endDate) {
            try {
                if ($type === 'daily' || $type === 'all') {
                    $this->aggregationService->updateDailySummaries($currentDate);
                }

                if ($type === 'weekly' || $type === 'all') {
                    $this->aggregationService->updateWeeklySummaries($currentDate);
                }

                if ($type === 'monthly' || $type === 'all') {
                    $this->aggregationService->updateMonthlySummaries($currentDate);
                }

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                \Illuminate\Support\Facades\Log::error("Rebuild failed for {$currentDate->toDateString()}: " . $e->getMessage());
            }

            $progressBar->advance();
            $currentDate->addDay();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("✅ Successful: {$successful}");
        $this->error("❌ Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
