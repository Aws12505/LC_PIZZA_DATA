<?php

use Illuminate\Support\Facades\Schedule;

/**
 * Laravel 12 Console Routes and Scheduling
 * 
 * Commands are auto-discovered from app/Console/Commands/
 * Schedules defined here replace the old Kernel.php
 * 
 * AGGREGATION CHAIN:
 * RAW DATA → HOURLY → DAILY → WEEKLY → MONTHLY → QUARTERLY → YEARLY
 */

// ════════════════════════════════════════════════════════════════════════════════════════════
// IMPORT SCHEDULES
// ════════════════════════════════════════════════════════════════════════════════════════════

// Primary import - yesterday's data at 9:20 AM ET
Schedule::command('import:daily-data --yesterday')
    ->dailyAt('09:20')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/import-daily-data.log'))
    ->name('import-primary')
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Primary import completed successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Primary import failed');
        // TODO: Send alert email/Slack notification
    });

// Secondary import - catch late updates at 10:20 AM ET
Schedule::command('import:daily-data --yesterday')
    ->dailyAt('10:20')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/import-daily-data-secondary.log'))
    ->name('import-secondary');

// ════════════════════════════════════════════════════════════════════════════════════════════
// AGGREGATION SCHEDULES
// ════════════════════════════════════════════════════════════════════════════════════════════

// Update hourly aggregations after imports (10:30 AM ET) - BUILDS FROM RAW DATA
Schedule::command('aggregation:update --type=hourly')
    ->dailyAt('10:30')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/aggregation-hourly.log'))
    ->name('aggregation-hourly')
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Hourly aggregation completed successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Hourly aggregation failed');
        // TODO: Send alert
    });

// Update daily aggregations after hourly (10:45 AM ET) - BUILDS FROM HOURLY
Schedule::command('aggregation:update --type=daily')
    ->dailyAt('10:45')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/aggregation-daily.log'))
    ->name('aggregation-daily')
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Daily aggregation completed successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Daily aggregation failed');
        // TODO: Send alert
    });

// Update weekly aggregations every Tuesday at 3:00 AM ET - BUILDS FROM DAILY
Schedule::command('aggregation:update --type=weekly')
    ->weekly()
    ->tuesdays()
    ->at('03:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/aggregation-weekly.log'))
    ->name('aggregation-weekly');

// Update monthly aggregations on 1st of month at 4:00 AM ET - BUILDS FROM WEEKLY
Schedule::command('aggregation:update --type=monthly')
    ->monthlyOn(1, '04:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/aggregation-monthly.log'))
    ->name('aggregation-monthly');

// Update quarterly aggregations on 1st day of quarter at 4:30 AM ET - BUILDS FROM MONTHLY
Schedule::command('aggregation:update --type=quarterly')
    ->quarterly()
    ->at('04:30')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/aggregation-quarterly.log'))
    ->name('aggregation-quarterly');

// Update yearly aggregations on January 1st at 5:00 AM ET - BUILDS FROM QUARTERLY
Schedule::command('aggregation:update --type=yearly')
    ->yearlyOn(1, 1, '05:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/aggregation-yearly.log'))
    ->name('aggregation-yearly');

// ════════════════════════════════════════════════════════════════════════════════════════════
// PARTITION SCHEDULES
// ════════════════════════════════════════════════════════════════════════════════════════════

// Archive old data daily at 2:00 AM ET (moves 91+ day old data)
Schedule::command('partition:archive-data')
    ->dailyAt('02:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/partition-archive.log'))
    ->name('partition-archive');

// Optimize tables monthly on 1st at 5:30 AM ET
Schedule::command('partition:optimize --analyze')
    ->monthlyOn(1, '05:30')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/partition-optimize.log'))
    ->name('partition-optimize');

// ════════════════════════════════════════════════════════════════════════════════════════════
// VALIDATION SCHEDULES
// ════════════════════════════════════════════════════════════════════════════════════════════

// Validate data daily at 11:00 AM ET (after imports and aggregations)
Schedule::command('validation:check-data')
    ->dailyAt('11:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/validation-check.log'))
    ->name('validation-daily')
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Data validation failed - check logs');
        // TODO: Send alert
    });

// ════════════════════════════════════════════════════════════════════════════════════════════
// MAINTENANCE SCHEDULES
// ════════════════════════════════════════════════════════════════════════════════════════════

// Clear old logs weekly (keep last 30 days)
Schedule::call(function () {
    $logPath = storage_path('logs');
    $files = glob("{$logPath}/*.log");
    $cutoff = now()->subDays(30);

    foreach ($files as $file) {
        if (filemtime($file) < $cutoff->timestamp) {
            @unlink($file);
        }
    }
})
    ->weekly()
    ->tuesdays()
    ->at('01:00')
    ->timezone('America/New_York')
    ->name('clear-old-logs');

// Cleanup temporary uploads every 5 minutes
Schedule::command('uploads:cleanup-temp')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/cleanup-temp-uploads.log'))
    ->name('cleanup-temp-uploads');

// ════════════════════════════════════════════════════════════════════════════════════════════
// SCHEDULER INFO
// ════════════════════════════════════════════════════════════════════════════════════════════

/**
 * To run the scheduler, add this to cron:
 * 
 * * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
 * 
 * Useful commands:
 *   php artisan schedule:list      # List all scheduled tasks
 *   php artisan schedule:work      # Run scheduler in foreground (testing)
 *   php artisan schedule:test      # Test scheduler without running
 * 
 * AGGREGATION CHAIN:
 *   RAW DATA → HOURLY → DAILY → WEEKLY → MONTHLY → QUARTERLY → YEARLY
 * 
 * Daily Timeline (Eastern Time):
 *   01:00 AM → Clear old logs (Tuesdays)
 *   02:00 AM → Archive old data (partition)
 *   03:00 AM → Update weekly aggregations (Tuesdays)
 *   04:00 AM → Update monthly aggregations (1st of month)
 *   04:30 AM → Update quarterly aggregations (1st day of quarter)
 *   05:00 AM → Update yearly aggregations (January 1st)
 *   05:30 AM → Optimize tables (1st of month)
 *   09:20 AM → Primary import (raw data)
 *   10:20 AM → Secondary import (catch late updates)
 *   10:30 AM → Update hourly aggregations (from raw data)
 *   10:45 AM → Update daily aggregations (from hourly)
 *   11:00 AM → Validate data
 *   Every 5m → Cleanup temporary uploads
 */
