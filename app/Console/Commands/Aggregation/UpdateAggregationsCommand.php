<?php

namespace App\Console\Commands\Aggregation;

use App\Services\Aggregation\AggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Update aggregation tables (daily, weekly, monthly summaries)
 * 
 * Usage:
 *   php artisan aggregation:update
 *   php artisan aggregation:update --date=2025-11-29
 *   php artisan aggregation:update --type=weekly
 *   php artisan aggregation:update --type=all
 */
class UpdateAggregationsCommand extends Command
{
    protected $signature = 'aggregation:update 
                            {--date= : Date to update (Y-m-d format, defaults to yesterday)}
                            {--type=daily : Type of aggregation (daily, weekly, monthly, all)}';

    protected $description = 'Update aggregation tables (summaries)';

    protected AggregationService $aggregationService;

    public function __construct(AggregationService $aggregationService)
    {
        parent::__construct();
        $this->aggregationService = $aggregationService;
    }

    public function handle(): int
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  Update Aggregation Tables');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $date = $this->getDate();
        $type = $this->option('type');

        $this->info("📅 Date: {$date->format('Y-m-d (l)')}");
        $this->info("📊 Type: {$type}");
        $this->newLine();

        $startTime = microtime(true);

        try {
            switch ($type) {
                case 'daily':
                    $this->updateDaily($date);
                    break;

                case 'weekly':
                    $this->updateWeekly($date);
                    break;

                case 'monthly':
                    $this->updateMonthly($date);
                    break;

                case 'all':
                    $this->updateAll($date);
                    break;

                default:
                    $this->error("Invalid aggregation type: {$type}");
                    $this->warn('Valid types: daily, weekly, monthly, all');
                    return self::FAILURE;
            }

            $duration = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info("✅ Aggregations updated successfully!");
            $this->info("⏱️  Duration: {$duration} seconds");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->error('❌ Aggregation update failed!');
            $this->error("⏱️  Duration: {$duration} seconds");
            $this->error('Error: ' . $e->getMessage());

            Log::error('Aggregation update failed', [
                'date' => $date->toDateString(),
                'type' => $type,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return self::FAILURE;
        }
    }

    protected function getDate(): Carbon
    {
        if ($this->option('date')) {
            try {
                return Carbon::parse($this->option('date'));
            } catch (\Exception $e) {
                $this->warn('Invalid date format. Using yesterday.');
                return Carbon::yesterday();
            }
        }

        return Carbon::yesterday();
    }

    protected function updateDaily(Carbon $date): void
    {
        $this->info('🔄 Updating daily summaries...');

        $this->aggregationService->updateDailySummaries($date);

        $this->info('  ✓ Daily store summaries updated');
        $this->info('  ✓ Daily item summaries updated');
    }

    protected function updateWeekly(Carbon $date): void
    {
        $weekStart = $date->copy()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();

        $this->info("🔄 Updating weekly summaries for week {$weekStart->format('M d')} - {$weekEnd->format('M d')}...");

        $this->aggregationService->updateWeeklySummaries($date);

        $this->info('  ✓ Weekly store summaries updated');
    }

    protected function updateMonthly(Carbon $date): void
    {
        $this->info("🔄 Updating monthly summaries for {$date->format('F Y')}...");

        $this->aggregationService->updateMonthlySummaries($date);

        $this->info('  ✓ Monthly store summaries updated');
    }

    protected function updateAll(Carbon $date): void
    {
        $this->updateDaily($date);
        $this->newLine();
        $this->updateWeekly($date);
        $this->newLine();
        $this->updateMonthly($date);
    }
}
