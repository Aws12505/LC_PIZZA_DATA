<?php

namespace App\Console\Commands\Partition;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Archive old data from operational to analytics database
 * 
 * Moves data older than cutoff days from operational (_hot) to analytics (_archive)
 * 
 * Usage:
 *   php artisan partition:archive-data
 *   php artisan partition:archive-data --dry-run
 *   php artisan partition:archive-data --cutoff-days=90
 */
class ArchiveDataCommand extends Command
{
    protected $signature = 'partition:archive-data 
                            {--dry-run : Show what would be archived without doing it}
                            {--cutoff-days=90 : Number of days to keep in operational DB}
                            {--table= : Archive specific table only}';

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
        $this->info('  Archive Old Data');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $cutoffDays = (int) $this->option('cutoff-days');
        $cutoffDate = Carbon::now()->subDays($cutoffDays);
        $dryRun = $this->option('dry-run');
        $specificTable = $this->option('table');

        $this->info("📅 Cutoff Date: {$cutoffDate->format('Y-m-d')}");
        $this->info("🗄️  Archiving data older than {$cutoffDays} days");

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
                $result = $this->archiveTable($table, $cutoffDate, $dryRun);
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

    protected function archiveTable(string $table, Carbon $cutoffDate, bool $dryRun): array
    {
        $hotTable = "{$table}_hot";
        $archiveTable = "{$table}_archive";

        // Count rows to archive
        $count = DB::connection('operational')
            ->table($hotTable)
            ->where('business_date', '<', $cutoffDate->toDateString())
            ->count();

        if ($count === 0) {
            $this->line("  ⊘ {$table}: No data to archive");
            return ['archived' => 0, 'deleted' => 0];
        }

        if ($dryRun) {
            $this->info("  ℹ {$table}: Would archive {$count} rows");
            return ['archived' => $count, 'deleted' => 0];
        }

        $this->info("  🔄 {$table}: Archiving {$count} rows...");

        DB::transaction(function() use ($hotTable, $archiveTable, $cutoffDate) {
            // Get data to archive
            $dataToArchive = DB::connection('operational')
                ->table($hotTable)
                ->where('business_date', '<', $cutoffDate->toDateString())
                ->get();

            if ($dataToArchive->isEmpty()) {
                return;
            }

            // Insert into archive (batch insert for performance)
            $chunks = $dataToArchive->chunk(1000);

            foreach ($chunks as $chunk) {
                $data = $chunk->map(fn($row) => (array) $row)->toArray();

                DB::connection('analytics')
                    ->table($archiveTable)
                    ->insertOrIgnore($data);
            }

            // Delete from operational
            DB::connection('operational')
                ->table($hotTable)
                ->where('business_date', '<', $cutoffDate->toDateString())
                ->delete();
        });

        $this->info("    ✓ Archived and removed {$count} rows");

        return ['archived' => $count, 'deleted' => $count];
    }
}
