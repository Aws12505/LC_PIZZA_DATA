<?php

namespace App\Console\Commands\Partition;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Archive old data from operational to analytics database
 * 
 * OPTIMIZED for millions of rows:
 * - Direct INSERT...SELECT queries
 * - Partition-based archiving
 * - Batch deletes by date range
 * - Progress tracking
 * 
 * Usage:
 *   php artisan partition:archive-data
 *   php artisan partition:archive-data --dry-run
 *   php artisan partition:archive-data --cutoff-days=90
 *   php artisan partition:archive-data --table=detail_orders --batch-days=30
 */
class ArchiveDataCommand extends Command
{
    protected $signature = 'partition:archive-data 
                            {--dry-run : Show what would be archived without doing it}
                            {--cutoff-days=90 : Number of days to keep in operational DB}
                            {--table= : Archive specific table only}
                            {--batch-days=30 : Number of days to archive per batch}
                            {--verify : Verify data after archiving}';

    protected $description = 'Archive old data from operational to analytics database';

    protected array $tables = [
        'detail_orders',
        'order_line',
        'summary_sales',
        'summary_items',
        'summary_transactions',
        'waste',
        'cash_management',
        'financial_views',
        'alta_inventory_cogs',
        'alta_inventory_ingredient_orders',
        'alta_inventory_ingredient_usage',
        'alta_inventory_waste'
    ];

    public function handle(): int
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  Archive Old Data - Optimized for Millions of Rows');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $cutoffDays = (int) $this->option('cutoff-days');
        $cutoffDate = Carbon::now()->subDays($cutoffDays);
        $dryRun = $this->option('dry-run');
        $specificTable = $this->option('table');
        $batchDays = (int) $this->option('batch-days');
        $verify = $this->option('verify');

        $this->info("📅 Cutoff Date: {$cutoffDate->format('Y-m-d')}");
        $this->info("🗄️  Archiving data older than {$cutoffDays} days");
        $this->info("📦 Batch Size: {$batchDays} days per iteration");

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No data will be moved');
        }

        $this->newLine();

        $tablesToProcess = $specificTable 
            ? [$specificTable] 
            : $this->tables;

        $totalArchived = 0;
        $totalDeleted = 0;
        $startTime = microtime(true);

        foreach ($tablesToProcess as $table) {
            try {
                $result = $this->archiveTable($table, $cutoffDate, $batchDays, $dryRun, $verify);
                $totalArchived += $result['archived'];
                $totalDeleted += $result['deleted'];
            } catch (\Exception $e) {
                $this->error("❌ Failed to archive {$table}: " . $e->getMessage());
                Log::error("Archive failed for {$table}", [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($dryRun) {
            $this->info("📊 WOULD archive {$totalArchived} rows from " . count($tablesToProcess) . " tables");
        } else {
            $this->info("✅ Archived {$totalArchived} rows");
            $this->info("🗑️  Deleted {$totalDeleted} rows from operational DB");
        }

        $this->info("⏱️  Duration: {$duration} seconds");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        return self::SUCCESS;
    }

    protected function archiveTable(
        string $table, 
        Carbon $cutoffDate, 
        int $batchDays, 
        bool $dryRun,
        bool $verify
    ): array {
        $hotTable = "{$table}_hot";
        $archiveTable = "{$table}_archive";

        // Get total count and date range
        $stats = DB::connection('operational')
            ->table($hotTable)
            ->where('business_date', '<', $cutoffDate->toDateString())
            ->selectRaw('COUNT(*) as total, MIN(business_date) as min_date, MAX(business_date) as max_date')
            ->first();

        if (!$stats || $stats->total === 0) {
            $this->line("  ⊘ {$table}: No data to archive");
            return ['archived' => 0, 'deleted' => 0];
        }

        $totalRows = $stats->total;
        $minDate = Carbon::parse($stats->min_date);
        $maxDate = Carbon::parse($stats->max_date);

        $this->info("  🔄 {$table}: {$totalRows} rows ({$minDate->toDateString()} → {$maxDate->toDateString()})");

        if ($dryRun) {
            $this->info("     Would archive in batches of {$batchDays} days");
            return ['archived' => $totalRows, 'deleted' => 0];
        }

        // Process in date-based batches
        $currentDate = $minDate->copy();
        $totalArchived = 0;
        $totalDeleted = 0;
        $batchNumber = 1;

        $progressBar = $this->output->createProgressBar(ceil($maxDate->diffInDays($minDate) / $batchDays));
        $progressBar->start();

        while ($currentDate <= $maxDate) {
            $batchEnd = $currentDate->copy()->addDays($batchDays - 1);
            if ($batchEnd > $maxDate) {
                $batchEnd = $maxDate->copy();
            }

            try {
                $result = $this->archiveDateRange(
                    $hotTable, 
                    $archiveTable, 
                    $currentDate, 
                    $batchEnd,
                    $verify
                );

                $totalArchived += $result['archived'];
                $totalDeleted += $result['deleted'];

                Log::info("Archived batch", [
                    'table' => $table,
                    'batch' => $batchNumber,
                    'start' => $currentDate->toDateString(),
                    'end' => $batchEnd->toDateString(),
                    'rows' => $result['archived']
                ]);

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("    ✗ Batch {$batchNumber} failed: " . $e->getMessage());
                Log::error("Batch archive failed", [
                    'table' => $table,
                    'batch' => $batchNumber,
                    'start' => $currentDate->toDateString(),
                    'end' => $batchEnd->toDateString(),
                    'error' => $e->getMessage()
                ]);
            }

            $progressBar->advance();
            $currentDate->addDays($batchDays);
            $batchNumber++;
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("    ✓ Archived {$totalArchived} rows and deleted {$totalDeleted} rows");

        return ['archived' => $totalArchived, 'deleted' => $totalDeleted];
    }

    /**
     * Archive a specific date range using direct INSERT...SELECT
     * This is MUCH faster than chunking for large datasets
     */
    protected function archiveDateRange(
        string $hotTable,
        string $archiveTable,
        Carbon $startDate,
        Carbon $endDate,
        bool $verify
    ): array {
        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();

        // Count rows in this range
        $count = DB::connection('operational')
            ->table($hotTable)
            ->whereBetween('business_date', [$startStr, $endStr])
            ->count();

        if ($count === 0) {
            return ['archived' => 0, 'deleted' => 0];
        }

        // Use transaction for consistency
        DB::transaction(function() use ($hotTable, $archiveTable, $startStr, $endStr) {
            // Direct INSERT...SELECT (fastest method for bulk data)
            DB::connection('analytics')->statement("
                INSERT IGNORE INTO {$archiveTable}
                SELECT * FROM operational.{$hotTable}
                WHERE business_date BETWEEN ? AND ?
            ", [$startStr, $endStr]);

            // Delete from operational
            DB::connection('operational')
                ->table($hotTable)
                ->whereBetween('business_date', [$startStr, $endStr])
                ->delete();
        });

        // Optional verification
        if ($verify) {
            $archivedCount = DB::connection('analytics')
                ->table($archiveTable)
                ->whereBetween('business_date', [$startStr, $endStr])
                ->count();

            $remainingCount = DB::connection('operational')
                ->table($hotTable)
                ->whereBetween('business_date', [$startStr, $endStr])
                ->count();

            if ($remainingCount > 0) {
                throw new \Exception("Verification failed: {$remainingCount} rows still in operational DB");
            }

            Log::info("Verification passed", [
                'table' => $hotTable,
                'archived' => $archivedCount,
                'remaining' => $remainingCount
            ]);
        }

        return ['archived' => $count, 'deleted' => $count];
    }
}
