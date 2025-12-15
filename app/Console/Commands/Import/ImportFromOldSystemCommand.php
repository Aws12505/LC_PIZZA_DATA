<?php

namespace App\Console\Commands\Import;

use App\Services\Main\LCReportDataService;
use App\Jobs\ProcessCsvImportJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use ZipArchive;

/**
 * Import data from old system API in batches
 * 
 * Fetches data from old system, saves as CSVs, and dispatches 
 * queue jobs for processing (async, won't hang!)
 * 
 * Usage:
 *   php artisan import:from-old-system --start=2025-11-01 --end=2025-11-30
 *   php artisan import:from-old-system --start=2025-11-01 --end=2025-11-30 --batch-days=7
 */
class ImportFromOldSystemCommand extends Command
{
    protected $signature = 'import:from-old-system 
                            {--start= : Start date (Y-m-d format)}
                            {--end= : End date (Y-m-d format)}
                            {--batch-days=7 : Number of days per batch request}
                            {--delay=5 : Seconds to wait between batch imports}';

    protected $description = 'Import data from old system API in date range batches';

    // Old system API configuration
    protected string $oldSystemBaseUrl;
    protected string $oldSystemApiKey;

    /**
     * Model mapping: old_system_export_name => [csv_filename, processor_class]
     * Matches the pattern from ManualCsvImportController
     */
    protected array $modelMap = [
        'detailOrder' => [
            'filename' => 'detail-orders.csv',
            'processor' => \App\Services\Import\Processors\DetailOrdersProcessor::class
        ],
        'orderLine' => [
            'filename' => 'detail-orderlines.csv',
            'processor' => \App\Services\Import\Processors\OrderLineProcessor::class
        ],
        'summarySale' => [
            'filename' => 'summary-sales.csv',
            'processor' => \App\Services\Import\Processors\SummarySalesProcessor::class
        ],
        'summaryItem' => [
            'filename' => 'summary-items.csv',
            'processor' => \App\Services\Import\Processors\SummaryItemsProcessor::class
        ],
        'summaryTransaction' => [
            'filename' => 'summary-transactions.csv',
            'processor' => \App\Services\Import\Processors\SummaryTransactionsProcessor::class
        ],
        'waste' => [
            'filename' => 'waste-report.csv',
            'processor' => \App\Services\Import\Processors\WasteProcessor::class
        ],
        'cashManagement' => [
            'filename' => 'cash-management.csv',
            'processor' => \App\Services\Import\Processors\CashManagementProcessor::class
        ],
        'financialView' => [
            'filename' => 'financial-views.csv',
            'processor' => \App\Services\Import\Processors\FinancialViewsProcessor::class
        ],
        'altaInventoryCogs' => [
            'filename' => 'inventory/cogs.csv',
            'processor' => \App\Services\Import\Processors\AltaInventoryCogsProcessor::class
        ],
        'altaInventoryIngredientOrder' => [
            'filename' => 'inventory/purchase-orders.csv',
            'processor' => \App\Services\Import\Processors\AltaInventoryIngredientOrdersProcessor::class
        ],
        'altaInventoryIngredientUsage' => [
            'filename' => 'inventory/ingredient-usage.csv',
            'processor' => \App\Services\Import\Processors\AltaInventoryIngredientUsageProcessor::class
        ],
        'altaInventoryWaste' => [
            'filename' => 'inventory/waste.csv',
            'processor' => \App\Services\Import\Processors\AltaInventoryWasteProcessor::class
        ],
    ];

    public function __construct()
    {
        parent::__construct();

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

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $totalBatches = (int) ceil($totalDays / $batchDays);

        $this->info("📅 Date Range: {$startDate->toDateString()} to {$endDate->toDateString()}");
        $this->info("📊 Total Days: {$totalDays}");
        $this->info("📦 Batch Size: {$batchDays} days");
        $this->info("🔢 Total Batches: {$totalBatches}");
        $this->info("⏱️  Delay Between Batches: {$delay} seconds");
        $this->info("⚡ Processing: Jobs will be queued (async)");
        $this->newLine();

        if (!$this->confirm('Do you want to continue?', true)) {
            $this->warn('Import cancelled');
            return self::SUCCESS;
        }

        $this->newLine();

        $successful = 0;
        $failed = 0;
        $totalJobs = 0;

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
                $jobsDispatched = $this->importBatch($currentDate, $batchEnd);

                if ($jobsDispatched > 0) {
                    $successful++;
                    $totalJobs += $jobsDispatched;
                    $this->info("  ✓ Dispatched {$jobsDispatched} jobs");
                } else {
                    $failed++;
                    $this->error("  ✗ No jobs dispatched");
                }

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

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  Import Results');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("✅ Successful Batches: {$successful}");
        $this->error("❌ Failed Batches: {$failed}");
        $this->info("📦 Total Batches: {$totalBatches}");
        $this->info("⚡ Total Jobs Queued: {$totalJobs}");
        $this->newLine();
        $this->info("💡 Jobs are processing in the background. Monitor with:");
        $this->info("   php artisan queue:work");
        $this->info("   Check logs: storage/logs/laravel.log");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function validateInputs(): bool
    {
        if (!$this->option('start') || !$this->option('end')) {
            $this->error('Both --start and --end dates are required');
            return false;
        }

        if (empty($this->oldSystemBaseUrl)) {
            $this->error('Old system API base URL not configured');
            return false;
        }

        if (empty($this->oldSystemApiKey)) {
            $this->error('Old system API key not configured');
            return false;
        }

        return true;
    }

    /**
     * Import a batch and dispatch queue jobs
     * Returns number of jobs dispatched
     */
    protected function importBatch(Carbon $startDate, Carbon $endDate): int
    {
        $uploadId = uniqid('import_', true);
        $storagePath = storage_path("app/uploads/{$uploadId}");

        try {
            // Create storage directory
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            $csvFiles = [];

            // Fetch and save CSV files from old system
            foreach ($this->modelMap as $modelName => $config) {
                try {
                    $data = $this->fetchFromOldSystem($modelName, $startDate, $endDate);

                    if (empty($data)) {
                        $this->line("  ⚠ {$modelName}: No data");
                        continue;
                    }

                    // Save as CSV file
                    $csvPath = $storagePath . '/' . $config['filename'];
                    $this->saveToCsv($csvPath, $data);

                    $csvFiles[$config['filename']] = [
                        'path' => $csvPath,
                        'processor' => $config['processor'],
                        'records' => count($data)
                    ];

                    $this->line("  ✓ {$modelName}: " . number_format(count($data)) . " records");

                } catch (\Exception $e) {
                    $this->error("  ✗ {$modelName}: " . $e->getMessage());
                    Log::error("Failed to fetch {$modelName}", [
                        'exception' => $e->getMessage(),
                        'start' => $startDate->toDateString(),
                        'end' => $endDate->toDateString()
                    ]);
                }
            }

            if (empty($csvFiles)) {
                return 0;
            }

            // Initialize progress tracking (same pattern as ManualCsvImportController)
            Cache::put("import_progress_{$uploadId}", [
                'status' => 'queued',
                'total_files' => count($csvFiles),
                'processed_files' => 0,
                'total_rows' => 0,
                'current_file' => null,
                'results' => [],
                'storage_path' => $storagePath,
                'started_at' => now()->toISOString(),
                'source' => 'old_system_import',
                'date_range' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString()
                ]
            ], 7200); // 2 hours TTL

            // Dispatch jobs for each CSV file
            $jobsDispatched = 0;
            foreach ($csvFiles as $filename => $info) {
                ProcessCsvImportJob::dispatch(
                    $uploadId,
                    $info['path'],
                    $filename,
                    $info['processor'],
                    count($csvFiles)
                );

                $jobsDispatched++;
            }

            Log::info("Old system import jobs dispatched", [
                'upload_id' => $uploadId,
                'total_files' => count($csvFiles),
                'jobs_dispatched' => $jobsDispatched,
                'date_range' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString()
                ]
            ]);

            return $jobsDispatched;

        } catch (\Exception $e) {
            Log::error("Batch import failed", [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'error' => $e->getMessage()
            ]);

            // Cleanup on failure
            if (is_dir($storagePath)) {
                $this->deleteDirectory($storagePath);
            }

            throw $e;
        }
    }

    protected function fetchFromOldSystem(string $model, Carbon $startDate, Carbon $endDate): array
    {
        $url = "{$this->oldSystemBaseUrl}/export/{$model}/json/{$startDate->toDateString()}/{$endDate->toDateString()}";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->oldSystemApiKey}",
                'Accept' => 'application/json',
            ])
            ->timeout(120)
            ->retry(3, 100)
            ->get($url);

            if (!$response->successful()) {
                throw new \Exception("API returned status {$response->status()}");
            }

            $result = $response->json();

            // Handle response format: {"success":true,"record_count":X,"data":[...]}
            if (isset($result['success']) && $result['success'] === true) {
                return $result['data'] ?? [];
            }

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

    protected function saveToCsv(string $filepath, array $data): void
    {
        if (empty($data)) {
            return;
        }

        // Ensure directory exists
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($filepath, 'w');
        if ($handle === false) {
            throw new \Exception("Cannot create CSV file: {$filepath}");
        }

        // Write header
        $headers = array_keys($data[0]);
        fputcsv($handle, $headers);

        // Write data rows
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    protected function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }

        @rmdir($path);
    }
}