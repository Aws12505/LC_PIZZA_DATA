<?php

namespace App\Console\Commands\Aggregation;

use App\Services\Aggregation\AggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Update aggregations for a specific date
 * Usage:
 *   php artisan aggregation:update
 *   php artisan aggregation:update --date=2025-01-15
 *   php artisan aggregation:update --date=2025-01-15 --type=hourly
 *   php artisan aggregation:update --date=2025-01-15 --type=all
 */
class UpdateAggregationsCommand extends Command
{
    protected $signature = 'aggregation:update
                            {--date= : Date to update (Y-m-d), defaults to yesterday}
                            {--type=all : Type: hourly, daily, weekly, monthly, quarterly, yearly, all}';

    protected $description = 'Update aggregations for a specific date';

    public function __construct(
        protected AggregationService $aggregationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->newLine();
        $this->info('📊 UPDATE AGGREGATIONS');
        $this->line(str_repeat('═', 80));

        $date = $this->getDate();
        $type = $this->option('type');
        $start = microtime(true);

        $this->table(['📅 Date', '📊 Type'], [[$date->format('Y-m-d l'), $type]]);
        $this->newLine();

        try {
            match ($type) {
                'hourly' => $this->updateHourly($date),
                'daily' => $this->updateDaily($date),
                'weekly' => $this->updateWeekly($date),
                'monthly' => $this->updateMonthly($date),
                'quarterly' => $this->updateQuarterly($date),
                'yearly' => $this->updateYearly($date),
                'all' => $this->updateAll($date),
                default => throw new \Exception("Invalid type '{$type}'. Use: hourly, daily, weekly, monthly, quarterly, yearly, all")
            };

            $time = round(microtime(true) - $start, 2);
            $this->info("✅ COMPLETE - {$time}s");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $time = round(microtime(true) - $start, 2);
            $this->error("❌ FAILED - {$time}s");
            $this->error($e->getMessage());
            Log::error('Update failed', ['date' => $date->toDateString(), 'type' => $type, 'error' => $e]);
            return self::FAILURE;
        }
    }

    protected function getDate(): Carbon
    {
        return $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();
    }

    protected function updateHourly(Carbon $date): void
    {
        $this->info('🔄 Hourly summaries (from raw data)...');
        $this->aggregationService->updateHourlySummaries($date);
        $this->info(' ✅ Hourly');
    }

    protected function updateDaily(Carbon $date): void
    {
        $this->info('🔄 Daily summaries (from hourly)...');
        $this->aggregationService->updateDailySummaries($date);
        $this->info(' ✅ Daily');
    }

    protected function updateWeekly(Carbon $date): void
    {
        $w1 = $date->copy()->startOfWeek()->format('M j');
        $w2 = $date->copy()->endOfWeek()->format('M j');
        $this->info("🔄 Weekly {$w1}-{$w2} (from daily)...");
        $this->aggregationService->updateWeeklySummaries($date);
        $this->info(' ✅ Weekly');
    }

    protected function updateMonthly(Carbon $date): void
    {
        $this->info("🔄 Monthly {$date->format('F Y')} (from weekly)...");
        $this->aggregationService->updateMonthlySummaries($date);
        $this->info(' ✅ Monthly');
    }

    protected function updateQuarterly(Carbon $date): void
    {
        $q = ceil($date->month / 3);
        $this->info("🔄 Quarterly {$date->format('Y')} Q{$q} (from monthly)...");
        $this->aggregationService->updateQuarterlySummaries($date);
        $this->info(' ✅ Quarterly');
    }

    protected function updateYearly(Carbon $date): void
    {
        $this->info("🔄 Yearly {$date->format('Y')} (from quarterly)...");
        $this->aggregationService->updateYearlySummaries($date);
        $this->info(' ✅ Yearly');
    }

    protected function updateAll(Carbon $date): void
    {
        $this->updateHourly($date); $this->newLine();
        $this->updateDaily($date); $this->newLine();
        $this->updateWeekly($date); $this->newLine();
        $this->updateMonthly($date); $this->newLine();
        $this->updateQuarterly($date); $this->newLine();
        $this->updateYearly($date);
    }
}
