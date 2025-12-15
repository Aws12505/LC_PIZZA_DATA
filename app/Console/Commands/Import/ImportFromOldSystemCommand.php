<?php

namespace App\Console\Commands\Import;

use App\Services\Main\LCReportDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Import data from old system API in batches
 * 
 * This command fetches data from the legacy system's export API endpoint,
 * saves it as CSV files, and uses the existing LCReportDataService 
 * to process them with proper duplicate handling.
 * 
 * Usage:
 *   php artisan import:from-old-system --start=2025-01-01 --end=2025-04-30
 *   php artisan import:from-old-system --start=2025-01-01 --end=2025-04-30 --batch-days=15
 */
class ImportFromOldSystemCommand extends Command
{
    protected $signature = 'import:from-old-system 
                            {--start= : Start date (Y-m-d format)}
                            {--end= : End date (Y-m-d format)}
                            {--batch-days=15 : Number of days per batch request}
                            {--delay=5 : Seconds to wait between batch imports}';

    protected $description = 'Import data from old system API in date range batches';

    protected LCReportDataService $importService;

    // Old system API configuration
    protected string $oldSystemBaseUrl;
    protected string $oldSystemApiKey;

    /**
     * Model mapping: old_system_export_name => csv_filename_pattern
     */
    protected array $modelMap = [
        'detailOrder'                          => 'detail-orders.csv',
        'orderLine'                            => 'detail-orderlines.csv',
        'summarySale'                          => 'summary-sales.csv',
        'summaryItem'                          => 'summary-items.csv',
        'summaryTransaction'                   => 'summary-transactions.csv',
        'waste'                                => 'waste-report.csv',
        'cashManagement'                       => 'cash-management.csv',
        'financialView'                        => 'financial-views.csv',
        'altaInventoryCogs'                    => 'inventory/cogs.csv',
        'altaInventoryIngredientOrder'         => 'inventory/purchase-orders.csv',
        'altaInventoryIngredientUsage'         => 'inventory/ingredient-usage.csv',
        'altaInventoryWaste'                   => 'inventory/waste.csv',
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

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $totalBatches = (int) ceil($totalDays / $batchDays);

        $this->info("📅 Date Range: {$startDate->toDateString()} to {$endDate->toDateString()}");
        $this->info("📊 Total Days: {$totalDays}");
        $this->info("📦 Batch Size: {$batchDays} days");
        $this->info("🔢 Total Batches: {$totalBatches}");
        $this->info("⏱️  Delay Between Batches: {$delay} seconds");
        $this->newLine();

        if (!$this->confirm('Do you want to continue?', true)) {
            $this->warn('Import cancelled');
            return self::SUCCESS;
        }

        $this->newLine();

        $successful = 0;
        $failed = 0;

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
                $success = $this->importBatch($currentDate, $batchEnd);

                if ($success) {
                    $successful++;
                    $this->info("  ✓ Batch complete");
                } else {
                    $failed++;
                    $this->error("  ✗ Batch failed");
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

    protected function importBatch(Carbon $startDate, Carbon $endDate): bool
    {
        $tempDir = storage_path('app/temp/import_' . uniqid());

        try {
            // Create temp directory
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Fetch and save CSV files from old system
            foreach ($this->modelMap as $modelName => $csvFilename) {
                try {
                    $data = $this->fetchFromOldSystem($modelName, $startDate, $endDate);

                    if (empty($data)) {
                        $this->line("  ⚠ {$modelName}: No data");
                        continue;
                    }

                    // Save as CSV file with proper structure
                    $csvPath = $tempDir . '/' . $csvFilename;
                    $this->saveToCsv($csvPath, $data);

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

            // Process the CSV files using existing LCReportDataService
            // This will handle all the upsert/replace logic, generated columns, etc.
            $this->info("  🔄 Processing with LCReportDataService...");

            // For each date in the batch, process the CSVs
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $result = $this->importService->processExtractedCsv($tempDir, $current->toDateString());
                $current->addDay();
            }

            return true;

        } catch (\Exception $e) {
            Log::error("Batch import failed", [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'error' => $e->getMessage()
            ]);
            throw $e;

        } finally {
            // Cleanup temp directory
            if (is_dir($tempDir)) {
                $this->deleteDirectory($tempDir);
            }
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

            // Handle the response format: {"success":true,"record_count":X,"data":[...]}
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