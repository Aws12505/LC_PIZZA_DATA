<?php

namespace App\Console\Commands\Partition;

use App\Services\Database\DatabaseRouter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Show partition statistics
 * 
 * Usage:
 *   php artisan partition:stats
 */
class PartitionStatsCommand extends Command
{
    protected $signature = 'partition:stats';

    protected $description = 'Show partition statistics';

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
        $this->info('  Partition Statistics');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $cutoffDate = DatabaseRouter::getCutoffDate();
        $this->info("📅 Cutoff Date: {$cutoffDate->toDateString()} (data older than this is in archive)");
        $this->newLine();

        $tableData = [];

        foreach ($this->tables as $table) {
            $stats = DatabaseRouter::getDataDistribution($table);

            $tableData[] = [
                $table,
                number_format($stats['hot_rows']),
                number_format($stats['archive_rows']),
                number_format($stats['total_rows']),
                $stats['hot_percentage'] . '%',
            ];
        }

        $this->table(
            ['Table', 'Hot Rows', 'Archive Rows', 'Total Rows', 'Hot %'],
            $tableData
        );

        $this->newLine();

        // Storage estimates
        $this->info('💾 Storage Information:');
        $this->showStorageInfo('operational', 'detail_orders_hot');
        $this->showStorageInfo('analytics', 'detail_orders_archive');

        return self::SUCCESS;
    }

    protected function showStorageInfo(string $connection, string $table): void
    {
        try {
            $result = DB::connection($connection)
                ->select("
                    SELECT 
                        ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.TABLES 
                    WHERE TABLE_NAME = ?
                ", [$table]);

            if (!empty($result)) {
                $sizeMb = $result[0]->size_mb ?? 0;
                $this->line("  • {$table}: {$sizeMb} MB");
            }
        } catch (\Exception $e) {
            // Silently skip if can't get storage info
        }
    }
}
