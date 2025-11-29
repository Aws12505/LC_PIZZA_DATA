<?php

namespace App\Console\Commands\Import;

use App\Services\Main\LCReportDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Import Little Caesars report data for a specific date
 * 
 * Usage:
 *   php artisan import:daily-data
 *   php artisan import:daily-data --date=2025-11-29
 *   php artisan import:daily-data --yesterday
 */
class ImportDailyDataCommand extends Command
{
    protected $signature = 'import:daily-data 
                            {--date= : Date to import (Y-m-d format)}
                            {--yesterday : Import yesterdays data}
                            {--retry : Retry import if it fails}';

    protected $description = 'Import Little Caesars daily report data from API';

    protected LCReportDataService $importService;

    public function __construct(LCReportDataService $importService)
    {
        parent::__construct();
        $this->importService = $importService;
    }

    public function handle(): int
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  Little Caesars Daily Data Import');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $date = $this->getImportDate();

        $this->info('📅 Import Date: ' . $date->format('Y-m-d (l)'));
        $this->newLine();

        $startTime = microtime(true);

        $this->info('🚀 Starting import process...');

        try {
            $success = $this->importService->importReportData($date->toDateString());

            $duration = round(microtime(true) - $startTime, 2);

            if ($success) {
                $this->newLine();
                $this->info('✅ Import completed successfully!');
                $this->info("⏱️  Duration: {$duration} seconds");

                // Update yesterday's data as well (catch late updates)
                $this->updatePreviousDayData($date);

                return self::SUCCESS;
            } else {
                $this->newLine();
                $this->error('❌ Import failed!');
                $this->error("⏱️  Duration before failure: {$duration} seconds");
                $this->warn('💡 Check logs: storage/logs/laravel.log');

                if ($this->option('retry')) {
                    return $this->retryImport($date);
                }

                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->error('❌ Import failed with exception!');
            $this->error("⏱️  Duration: {$duration} seconds");
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();
            $this->warn('💡 Check logs for full stack trace');

            Log::error('Import command failed', [
                'date' => $date->toDateString(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return self::FAILURE;
        }
    }

    protected function getImportDate(): Carbon
    {
        if ($this->option('yesterday')) {
            return Carbon::yesterday();
        }

        if ($this->option('date')) {
            try {
                return Carbon::parse($this->option('date'));
            } catch (\Exception $e) {
                $this->error('Invalid date format. Using yesterday.');
                return Carbon::yesterday();
            }
        }

        return Carbon::yesterday();
    }

    protected function updatePreviousDayData(Carbon $importDate): void
    {
        $previousDay = $importDate->copy()->subDay();

        $this->newLine();
        $this->info("🔄 Updating data for {$previousDay->toDateString()} (previous day)...");

        try {
            $success = $this->importService->importReportData($previousDay->toDateString());

            if ($success) {
                $this->info('✅ Previous day data updated successfully');
            } else {
                $this->warn('⚠️  Failed to update previous day data');
            }
        } catch (\Exception $e) {
            $this->warn('⚠️  Failed to update previous day: ' . $e->getMessage());
        }
    }

    protected function retryImport(Carbon $date): int
    {
        $this->newLine();
        $this->warn('🔄 Retrying import in 10 seconds...');
        sleep(10);

        $this->info('🚀 Retry attempt...');

        try {
            $success = $this->importService->importReportData($date->toDateString());

            if ($success) {
                $this->info('✅ Retry successful!');
                return self::SUCCESS;
            } else {
                $this->error('❌ Retry failed');
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Retry failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
