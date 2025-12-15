<?php

namespace App\Console\Commands\Import;

use App\Services\Main\LCReportDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Import data from old system API in batches
 * 
 * This command fetches data from the legacy system's export API endpoint
 * and imports it to the new system in configurable date range batches.
 * 
 * Usage:
 *   php artisan import:from-old-system --start=2025-01-01 --end=2025-04-30
 *   php artisan import:from-old-system --start=2025-01-01 --end=2025-04-30 --batch-days=15
 *   php artisan import:from-old-system --start=2025-01-01 --end=2025-04-30 --batch-days=15 --delay=10
 */
class ImportFromOldSystemCommand extends Command
{
    protected $signature = 'import:from-old-system 
                            {--start= : Start date (Y-m-d format)}
                            {--end= : End date (Y-m-d format)}
                            {--batch-days=15 : Number of days per batch request}
                            {--delay=5 : Seconds to wait between batch imports}
                            {--skip-existing : Skip dates that already have data}
                            {--models=* : Specific models to import (default: all)}';

    protected $description = 'Import data from old system API in date range batches';

    protected LCReportDataService $importService;

    // Old system API configuration
    protected string $oldSystemBaseUrl;
    protected string $oldSystemApiKey;

    /**
     * Model mapping: old_system_export_name => new_system_table_base_name
     * Only includes models that exist in BOTH systems
     */
    protected array $modelMap = [
        'detailOrders'                        => 'detailOrders',
        'orderLine'                           => 'orderLine',
        'summarySales'                        => 'summarySales',
        'summaryItems'                        => 'summaryItems',
        'summaryTransactions'                 => 'summaryTransactions',
        'waste'                                => 'waste',
        'cashManagement'                      => 'cashManagement',
        'financialViews'                      => 'financialViews',
        'altaInventoryCogs'                  => 'altaInventoryCogs',
        'altaInventoryIngredientOrders'     => 'altaInventoryIngredientOrders',
        'altaInventoryIngredientUsage'      => 'altaInventoryIngredientUsage',
        'altaInventoryWaste'                 => 'altaInventoryWaste',
    ];

    public function __construct(LCReportDataService $importService)
    {
        parent::__construct();
        $this->importService = $importService;

        // Load old system configuration
        $this->oldSystemBaseUrl = config('services.old_api.base_url', 'http://localhost');
        $this->oldSystemApiKey = config('services.old_api.api_key', 'null_thing');
    }

    public function handle(): int
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  Import Data from Old System');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Validate inputs
        if (!$this->validateInputs()) {
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

        $batchDays = (int) $this->option('batch-days');
        $delay = (int) $this->option('delay');
        $skipExisting = $this->option('skip-existing');

        // Determine which models to import
        $requestedModels = $this->option('models');
        $modelsToImport = empty($requestedModels) 
            ? array_keys($this->modelMap) 
            : array_intersect(array_keys($this->modelMap), $requestedModels);

        if (empty($modelsToImport)) {
            $this->error('No valid models to import. Available models: ' . implode(', ', array_keys($this->modelMap)));
            return self::FAILURE;
        }

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $totalBatches = (int) ceil($totalDays / $batchDays);

        $this->info("📅 Date Range: {$startDate->toDateString()} to {$endDate->toDateString()}");
        $this->info("📊 Total Days: {$totalDays}");
        $this->info("📦 Batch Size: {$batchDays} days");
        $this->info("🔢 Total Batches: {$totalBatches}");
        $this->info("⏱️  Delay Between Batches: {$delay} seconds");
        $this->info("📋 Models: " . count($modelsToImport) . ' tables');
        $this->newLine();

        if (!$this->confirm('Do you want to continue?', true)) {
            $this->warn('Import cancelled');
            return self::SUCCESS;
        }

        $this->newLine();

        $successful = 0;
        $failed = 0;
        $skipped = 0;
        $totalRecords = 0;

        $progressBar = $this->output->createProgressBar($totalBatches);
        $progressBar->start();

        $currentDate = $startDate->copy();
        $batchNumber = 1;

        while ($currentDate <= $endDate) {
            $batchEnd = $currentDate->copy()->addDays($batchDays - 1);
            if ($batchEnd > $endDate) {
                $batchEnd = $endDate->copy();
            }

            $this->newLine();
            $this->info("\n[Batch {$batchNumber}/{$totalBatches}] {$currentDate->toDateString()} → {$batchEnd->toDateString()}");

            try {
                $result = $this->importBatch(
                    $currentDate,
                    $batchEnd,
                    $modelsToImport,
                    $skipExisting
                );

                $successful += $result['successful'];
                $failed += $result['failed'];
                $skipped += $result['skipped'];
                $totalRecords += $result['records'];

                $this->info("  ✓ Batch complete: {$result['successful']} models, {$result['records']} records");

            } catch (\Exception $e) {
                $failed++;
                $this->error("  ✗ Batch failed: " . $e->getMessage());
                Log::error("Import batch failed", [
                    'start' => $currentDate->toDateString(),
                    'end' => $batchEnd->toDateString(),
                    'exception' => $e->getMessage()
                ]);
            }

            $progressBar->advance();

            // Delay between batches
            if ($currentDate < $endDate && $delay > 0) {
                sleep($delay);
            }

            $currentDate->addDays($batchDays);
            $batchNumber++;
        }

        $progressBar->finish();
        $this->newLine(2);

        // Trigger aggregations after all imports
        $this->info('🔄 Updating aggregations...');
        try {
            $this->call('aggregation:rebuild', [
                '--start' => $startDate->toDateString(),
                '--end' => $endDate->toDateString(),
                '--type' => 'all'
            ]);
        } catch (\Exception $e) {
            $this->warn('⚠️  Aggregation update failed: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  Import Results');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("✅ Successful Models: {$successful}");
        $this->error("❌ Failed Models: {$failed}");
        $this->warn("⊘ Skipped Models: {$skipped}");
        $this->info("📊 Total Records: " . number_format($totalRecords));
        $this->info("📦 Total Batches: {$totalBatches}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function validateInputs(): bool
    {
        if (!$this->option('start') || !$this->option('end')) {
            $this->error('Both --start and --end dates are required');
            return false;
        }

        if (empty($this->oldSystemBaseUrl)) {
            $this->error('Old system API base URL not configured. Set OLD_API_BASE_URL in .env');
            return false;
        }

        if (empty($this->oldSystemApiKey)) {
            $this->error('Old system API key not configured. Set OLD_API_KEY in .env');
            return false;
        }

        return true;
    }

    protected function importBatch(
        Carbon $startDate,
        Carbon $endDate,
        array $modelsToImport,
        bool $skipExisting
    ): array {
        $successful = 0;
        $failed = 0;
        $skipped = 0;
        $totalRecords = 0;

        foreach ($modelsToImport as $oldModelName) {
            $newTableBase = $this->modelMap[$oldModelName];

            try {
                // Check if data exists
                if ($skipExisting && $this->hasData($newTableBase, $startDate, $endDate)) {
                    $this->line("  ⊘ {$oldModelName}: Already exists");
                    $skipped++;
                    continue;
                }

                // Fetch data from old system
                $data = $this->fetchFromOldSystem($oldModelName, $startDate, $endDate);

                if (empty($data)) {
                    $this->line("  ⚠ {$oldModelName}: No data");
                    $skipped++;
                    continue;
                }

                // Import to new system
                $recordCount = $this->importToNewSystem($newTableBase, $data);

                if ($recordCount > 0) {
                    $successful++;
                    $totalRecords += $recordCount;
                    $this->line("  ✓ {$oldModelName}: " . number_format($recordCount) . " records");
                } else {
                    $failed++;
                    $this->error("  ✗ {$oldModelName}: Import failed");
                }

            } catch (\Exception $e) {
                $failed++;
                $this->error("  ✗ {$oldModelName}: " . $e->getMessage());
                Log::error("Model import failed: {$oldModelName}", [
                    'exception' => $e->getMessage(),
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString()
                ]);
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
            'skipped' => $skipped,
            'records' => $totalRecords
        ];
    }

    protected function fetchFromOldSystem(string $model, Carbon $startDate, Carbon $endDate): array
    {
        // Build URL matching old system's export route pattern
        // Route: /export/{model}/json/{start}/{end}
        $url = "{$this->oldSystemBaseUrl}/export/{$model}/json/{$startDate->toDateString()}/{$endDate->toDateString()}";

        Log::info("Fetching from old system", [
            'model' => $model,
            'url' => $url,
            'start' => $startDate->toDateString(),
            'end' => $endDate->toDateString()
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->oldSystemApiKey}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(120)
            ->retry(3, 100) // Retry 3 times with 100ms delay
            ->get($url);

            if (!$response->successful()) {
                throw new \Exception("API returned status {$response->status()}: " . $response->body());
            }

            $result = $response->json();

            // Handle the response format from old system's ExportingService
            // Response format: {"success":true,"record_count":X,"data":[...]}
            if (isset($result['success']) && $result['success'] === true) {
                Log::info("Fetched {$result['record_count']} records from {$model}");
                return $result['data'] ?? [];
            }

            // Fallback for direct data array
            return is_array($result) ? $result : [];

        } catch (\Exception $e) {
            Log::error("Failed to fetch from old system", [
                'model' => $model,
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function importToNewSystem(string $tableBaseName, array $data): int
    {
        if (empty($data)) {
            return 0;
        }

        try {
            // Determine the appropriate table name and connection
            $cutoffDate = \App\Services\Database\DatabaseRouter::getCutoffDate();

            $hotInserted = 0;
            $archiveInserted = 0;

            // Batch insert data in chunks of 1000
            $chunks = array_chunk($data, 1000);

            foreach ($chunks as $chunk) {
                // Separate data by date - hot vs archive
                $hotData = [];
                $archiveData = [];

                foreach ($chunk as $row) {
                    // Ensure timestamps are set
                    if (!isset($row['created_at'])) {
                        $row['created_at'] = now();
                    }
                    if (!isset($row['updated_at'])) {
                        $row['updated_at'] = now();
                    }

                    if (isset($row['business_date'])) {
                        $businessDate = Carbon::parse($row['business_date']);

                        if ($businessDate >= $cutoffDate) {
                            $hotData[] = $row;
                        } else {
                            $archiveData[] = $row;
                        }
                    } else {
                        // If no business_date, default to hot
                        $hotData[] = $row;
                    }
                }

                // Insert to operational (hot) database
                if (!empty($hotData)) {
                    \Illuminate\Support\Facades\DB::connection('operational')
                        ->table("{$tableBaseName}_hot")
                        ->insertOrIgnore($hotData);
                    $hotInserted += count($hotData);
                }

                // Insert to analytics (archive) database
                if (!empty($archiveData)) {
                    \Illuminate\Support\Facades\DB::connection('analytics')
                        ->table("{$tableBaseName}_archive")
                        ->insertOrIgnore($archiveData);
                    $archiveInserted += count($archiveData);
                }
            }

            $total = $hotInserted + $archiveInserted;

            Log::info("Imported to {$tableBaseName}", [
                'hot' => $hotInserted,
                'archive' => $archiveInserted,
                'total' => $total
            ]);

            return $total;

        } catch (\Exception $e) {
            Log::error("Failed to import to new system", [
                'table' => $tableBaseName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function hasData(string $tableBaseName, Carbon $startDate, Carbon $endDate): bool
    {
        try {
            // Check hot table
            $hotCount = \Illuminate\Support\Facades\DB::connection('operational')
                ->table("{$tableBaseName}_hot")
                ->whereBetween('business_date', [
                    $startDate->toDateString(),
                    $endDate->toDateString()
                ])
                ->count();

            // Check archive table
            $archiveCount = \Illuminate\Support\Facades\DB::connection('analytics')
                ->table("{$tableBaseName}_archive")
                ->whereBetween('business_date', [
                    $startDate->toDateString(),
                    $endDate->toDateString()
                ])
                ->count();

            return ($hotCount + $archiveCount) > 0;

        } catch (\Exception $e) {
            return false;
        }
    }
}
