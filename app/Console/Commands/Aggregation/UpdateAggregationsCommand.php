<?php

namespace App\Console\Commands\Aggregation;

use App\Services\Aggregation\AggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateAggregationsCommand extends Command
{
    protected $signature = 'aggregation:update 
        {--date= : Date (Y-m-d, default yesterday)}
        {--type=daily : daily, weekly, monthly, quarterly, yearly, all}';

    protected $description = 'Update ALL aggregation levels';

    protected $aggregationService;

    public function __construct(AggregationService $aggregationService)
    {
        parent::__construct();
        $this->aggregationService = $aggregationService;
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
                'daily' => $this->updateDaily($date),
                'weekly' => $this->updateWeekly($date),
                'monthly' => $this->updateMonthly($date),
                'quarterly' => $this->updateQuarterly($date),
                'yearly' => $this->updateYearly($date),
                'all' => $this->updateAll($date),
                default => throw new \Exception("Invalid type '{$type}'. Use: daily, weekly, monthly, quarterly, yearly, all")
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

    protected function updateDaily(Carbon $date): void
    {
        $this->info('🔄 Daily summaries...');
        $this->aggregationService->updateDailySummaries($date);
        $this->info('  ✅ Daily');
    }

    protected function updateWeekly(Carbon $date): void
    {
        $w1 = $date->copy()->startOfWeek()->format('M j');
        $w2 = $date->copy()->endOfWeek()->format('M j');
        $this->info("🔄 Weekly {$w1}-{$w2}...");
        $this->aggregationService->updateWeeklySummaries($date);
        $this->info('  ✅ Weekly');
    }

    protected function updateMonthly(Carbon $date): void
    {
        $this->info("🔄 Monthly {$date->format('F Y')}...");
        $this->aggregationService->updateMonthlySummaries($date);
        $this->info('  ✅ Monthly');
    }

    protected function updateQuarterly(Carbon $date): void
    {
        $q = ceil($date->month / 3);
        $this->info("🔄 Quarterly {$date->format('Y')} Q{$q}...");
        $this->aggregationService->updateQuarterlySummaries($date);
        $this->info('  ✅ Quarterly');
    }

    protected function updateYearly(Carbon $date): void
    {
        $this->info("🔄 Yearly {$date->format('Y')}...");
        $this->aggregationService->updateYearlySummaries($date);
        $this->info('  ✅ Yearly');
    }

    protected function updateAll(Carbon $date): void
    {
        $this->updateDaily($date); $this->newLine();
        $this->updateWeekly($date); $this->newLine();
        $this->updateMonthly($date); $this->newLine();
        $this->updateQuarterly($date); $this->newLine();
        $this->updateYearly($date);
    }
}