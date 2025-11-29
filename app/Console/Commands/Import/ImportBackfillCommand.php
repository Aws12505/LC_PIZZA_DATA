<?php

namespace App\Console\Commands\Import;

use App\Services\Main\LCReportDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Backfill historical data for a date range
 * 
 * Usage:
 *   php artisan import:backfill --start=2025-01-01 --end=2025-01-31
 *   php artisan import:backfill --start=2025-01-01 --end=2025-01-31 --skip-existing
 */
class ImportBackfillCommand extends Command
{
    protected $signature = 'import:backfill 
                            {--start= : Start date (Y-m-d format)}
                            {--end= : End date (Y-m-d format)}
                            {--skip-existing : Skip dates that already have data}
                            {--delay=5 : Seconds to wait between imports}';

    protected $description = 'Backfill historical data for a date range';

    protected LCReportDataService $importService;

    public function __construct(LCReportDataService $importService)
    {
        parent::__construct();
        $this->importService = $importService;
    }

    public function handle(): int
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  Backfill Historical Data');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if (!$this->option('start') || !$this->option('end')) {
            $this->error('Both --start and --end dates are required');
            return self::FAILURE;
        }

        try {
            $startDate = Carbon::parse($this->option('start'));
            $endDate = Carbon::parse($this->option('end'));
        } catch (\Exception $e) {
            $this->error('Invalid date format. Use Y-m-d (e.g., 2025-01-01)');
            return self::FAILURE;
        }

        if ($startDate > $endDate) {
            $this->error('Start date must be before end date');
            return self::FAILURE;
        }

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $delay = (int) $this->option('delay');

        $this->info("📅 Start Date: {$startDate->toDateString()}");
        $this->info("📅 End Date: {$endDate->toDateString()}");
        $this->info("📊 Total Days: {$totalDays}");
        $this->info("⏱️  Delay Between Imports: {$delay} seconds");
        $this->newLine();

        if (!$this->confirm('Do you want to continue?', true)) {
            $this->warn('Backfill cancelled');
            return self::SUCCESS;
        }

        $this->newLine();

        $successful = 0;
        $failed = 0;
        $skipped = 0;

        $progressBar = $this->output->createProgressBar($totalDays);
        $progressBar->start();

        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateString = $currentDate->toDateString();

            // Check if data exists and should skip
            if ($this->option('skip-existing') && $this->hasData($dateString)) {
                $skipped++;
                $progressBar->advance();
                $currentDate->addDay();
                continue;
            }

            try {
                $success = $this->importService->importReportData($dateString);

                if ($success) {
                    $successful++;
                } else {
                    $failed++;
                    Log::warning("Backfill failed for {$dateString}");
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error("Backfill exception for {$dateString}: " . $e->getMessage());
            }

            $progressBar->advance();

            // Delay between imports to avoid overwhelming API
            if ($currentDate < $endDate) {
                sleep($delay);
            }

            $currentDate->addDay();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  Backfill Results');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("✅ Successful: {$successful}");
        $this->error("❌ Failed: {$failed}");
        $this->warn("⊘ Skipped: {$skipped}");
        $this->info("📊 Total: {$totalDays}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function hasData(string $date): bool
    {
        try {
            $count = \Illuminate\Support\Facades\DB::connection('operational')
                ->table('detail_orders_hot')
                ->where('business_date', $date)
                ->count();

            return $count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
