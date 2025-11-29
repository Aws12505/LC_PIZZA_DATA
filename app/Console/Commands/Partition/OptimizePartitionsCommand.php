<?php

namespace App\Console\Commands\Partition;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Optimize database tables and partitions
 * 
 * Usage:
 *   php artisan partition:optimize
 *   php artisan partition:optimize --tables=detail_orders,order_line
 */
class OptimizePartitionsCommand extends Command
{
    protected $signature = 'partition:optimize 
                            {--tables= : Comma-separated list of tables to optimize}
                            {--analyze : Run ANALYZE TABLE as well}';

    protected $description = 'Optimize database tables and partitions';

    protected array $defaultTables = [
        'detail_orders_hot',
        'order_line_hot',
        'summary_sales_hot',
        'detail_orders_archive',
        'order_line_archive',
        'summary_sales_archive',
    ];

    public function handle(): int
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  Optimize Database Tables');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $tables = $this->getTablesToOptimize();
        $runAnalyze = $this->option('analyze');

        $this->info("📊 Tables to optimize: " . count($tables));
        $this->newLine();

        $progressBar = $this->output->createProgressBar(count($tables));
        $progressBar->start();

        foreach ($tables as $table) {
            try {
                // Determine connection
                $connection = str_contains($table, '_hot') ? 'operational' : 'analytics';

                // Run OPTIMIZE TABLE
                DB::connection($connection)
                    ->statement("OPTIMIZE TABLE {$table}");

                // Run ANALYZE TABLE if requested
                if ($runAnalyze) {
                    DB::connection($connection)
                        ->statement("ANALYZE TABLE {$table}");
                }

                $progressBar->advance();
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to optimize {$table}: " . $e->getMessage());
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('✅ Table optimization complete');

        return self::SUCCESS;
    }

    protected function getTablesToOptimize(): array
    {
        if ($this->option('tables')) {
            return explode(',', $this->option('tables'));
        }

        return $this->defaultTables;
    }
}
