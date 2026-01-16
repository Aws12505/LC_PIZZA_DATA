<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCsvImportJob;
use App\Jobs\ProcessAggregationJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

class ManualCsvImportController extends Controller
{
    // ---- Custom log channel name (configure it in config/logging.php, see notes below)
    private string $traceChannel = 'csv_import_trace';

    protected array $processorMap = [
        'detail_orders' => \App\Services\Import\Processors\DetailOrdersProcessor::class,
        'order_lines' => \App\Services\Import\Processors\OrderLineProcessor::class,
        'summary_sales' => \App\Services\Import\Processors\SummarySalesProcessor::class,
        'summary_items' => \App\Services\Import\Processors\SummaryItemsProcessor::class,
        'summary_transactions' => \App\Services\Import\Processors\SummaryTransactionsProcessor::class,
        'waste' => \App\Services\Import\Processors\WasteProcessor::class,
        'cash_management' => \App\Services\Import\Processors\CashManagementProcessor::class,
        'financial_views' => \App\Services\Import\Processors\FinancialViewsProcessor::class,
        'inventory_cogs' => \App\Services\Import\Processors\AltaInventoryCogsProcessor::class,
        'inventory_orders' => \App\Services\Import\Processors\AltaInventoryIngredientOrdersProcessor::class,
        'inventory_usage' => \App\Services\Import\Processors\AltaInventoryIngredientUsageProcessor::class,
        'inventory_waste' => \App\Services\Import\Processors\AltaInventoryWasteProcessor::class,
    ];

    public function index()
    {
        return view('manual-csv-import', [
            'processors' => [
                'detail_orders' => 'Detail Orders',
                'order_lines' => 'Order Lines',
                'summary_sales' => 'Summary Sales',
                'summary_items' => 'Summary Items',
                'summary_transactions' => 'Summary Transactions',
                'waste' => 'Waste Report',
                'cash_management' => 'Cash Management',
                'financial_views' => 'Financial Views',
                'inventory_cogs' => 'Inventory COGS',
                'inventory_orders' => 'Inventory Orders',
                'inventory_usage' => 'Inventory Usage',
                'inventory_waste' => 'Inventory Waste',
            ]
        ]);
    }

    /**
     * NEW: Inspect ZIP contents without processing
     */
    public function inspectZip(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:zip|max:1048576'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $file = $request->file('file');
            $tempId = uniqid('temp_', true);
            $tempPath = storage_path("app/temp/{$tempId}");
            mkdir($tempPath, 0755, true);

            Log::channel($this->traceChannel)->info('inspectZip: received zip', [
                'temp_id' => $tempId,
                'client_original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'tmp_realpath' => $file->getRealPath(),
                'temp_path' => $tempPath,
            ]);

            // Extract ZIP
            $zipPath = $tempPath . '/' . $file->getClientOriginalName();
            $file->move($tempPath, $file->getClientOriginalName());

            Log::channel($this->traceChannel)->info('inspectZip: zip moved', [
                'temp_id' => $tempId,
                'zip_path' => $zipPath,
                'zip_exists' => file_exists($zipPath),
                'dir_listing' => $this->safeDirList($tempPath),
            ]);

            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \Exception("Failed to open ZIP");
            }

            $zip->extractTo($tempPath);
            $zip->close();
            @unlink($zipPath);

            Log::channel($this->traceChannel)->info('inspectZip: zip extracted', [
                'temp_id' => $tempId,
                'temp_path' => $tempPath,
                'dir_listing_after_extract' => $this->safeDirList($tempPath),
            ]);

            // Find all CSVs
            $csvFiles = $this->findCsvFiles($tempPath);

            Log::channel($this->traceChannel)->info('inspectZip: csv discovery', [
                'temp_id' => $tempId,
                'csv_count' => count($csvFiles),
                'csv_full_paths' => $csvFiles,
            ]);

            $csvList = array_map(function ($path) use ($tempPath) {
                $size = @filesize($path);
                return [
                    'name' => basename($path),
                    'size' => $size ?: null,
                    'size_mb' => $size ? round($size / 1024 / 1024, 2) : null,
                ];
            }, $csvFiles);

            // Store temp path for later processing
            Cache::put("temp_zip_{$tempId}", [
                'path' => $tempPath,
                'files' => $csvFiles
            ], 600);

            Log::channel($this->traceChannel)->info('inspectZip: cached temp data', [
                'temp_id' => $tempId,
                'cache_key' => "temp_zip_{$tempId}",
                'cached_file_basenames' => array_map('basename', $csvFiles),
            ]);

            return response()->json([
                'success' => true,
                'temp_id' => $tempId,
                'csv_files' => $csvList
            ]);
        } catch (\Exception $e) {
            Log::channel($this->traceChannel)->error('inspectZip failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|mimes:csv,txt|max:1048576',
            'mappings' => 'required|array|min:1',
            'mappings.*' => 'required|string'
        ]);

        // Support ZIP temp_id flow
        if ($request->has('temp_id')) {
            Log::channel($this->traceChannel)->info('upload: temp_id flow detected', [
                'temp_id' => $request->input('temp_id'),
            ]);
            return $this->uploadFromTemp($request);
        }

        if ($validator->fails()) {
            Log::channel($this->traceChannel)->warning('upload: validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $files = $request->file('files');
            $mappingsRaw = $request->input('mappings');
            $mappings = json_decode($mappingsRaw, true);

            $uploadId = uniqid('import_', true);
            $storagePath = storage_path("app/uploads/{$uploadId}");
            mkdir($storagePath, 0755, true);

            Log::channel($this->traceChannel)->info('upload: request received', [
                'upload_id' => $uploadId,
                'storage_path' => $storagePath,
                'files_count' => is_array($files) ? count($files) : 0,
                'mappings_raw_type' => gettype($mappingsRaw),
                'mappings_raw_preview' => is_string($mappingsRaw) ? mb_substr($mappingsRaw, 0, 500) : null,
                'mappings_decoded_keys' => is_array($mappings) ? array_keys($mappings) : null,
            ]);

            // Move all CSV files
            $csvFiles = [];
            foreach ($files as $idx => $file) {
                $originalName = $file->getClientOriginalName();

                Log::channel($this->traceChannel)->info('upload: file received', [
                    'upload_id' => $uploadId,
                    'index' => $idx,
                    'original_name' => $originalName,
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'tmp_realpath' => $file->getRealPath(),
                    'tmp_exists' => $file->getRealPath() ? file_exists($file->getRealPath()) : null,
                ]);

                $file->move($storagePath, $originalName);
                $savedPath = $storagePath . '/' . $originalName;

                Log::channel($this->traceChannel)->info('upload: file moved', [
                    'upload_id' => $uploadId,
                    'original_name' => $originalName,
                    'saved_path' => $savedPath,
                    'saved_exists' => file_exists($savedPath),
                    'saved_size' => file_exists($savedPath) ? filesize($savedPath) : null,
                    'dir_listing_now' => $this->safeDirList($storagePath),
                ]);

                $csvFiles[$originalName] = $savedPath;
            }

            // Initialize progress
            Cache::put("import_progress_{$uploadId}", [
                'status' => 'queued',
                'total_files' => count($csvFiles),
                'processed_files' => 0,
                'total_rows' => 0,
                'current_file' => null,
                'results' => [],
                'storage_path' => $storagePath,
                'started_at' => now()->toISOString()
            ], 3600);

            Log::channel($this->traceChannel)->info('upload: progress initialized', [
                'upload_id' => $uploadId,
                'cache_key' => "import_progress_{$uploadId}",
                'total_files' => count($csvFiles),
                'storage_path' => $storagePath,
                'dir_listing_before_dispatch' => $this->safeDirList($storagePath),
            ]);

            // Dispatch jobs
            $dispatched = 0;
            foreach ($csvFiles as $originalName => $csvPath) {
                $mappingKey = $mappings[$originalName] ?? null;
                $processorClass = ($mappingKey && isset($this->processorMap[$mappingKey])) ? $this->processorMap[$mappingKey] : null;

                Log::channel($this->traceChannel)->info('upload: dispatch decision', [
                    'upload_id' => $uploadId,
                    'original_name' => $originalName,
                    'csv_path' => $csvPath,
                    'csv_path_exists' => file_exists($csvPath),
                    'mapping_key' => $mappingKey,
                    'processor_class' => $processorClass,
                ]);

                if (!$processorClass) {
                    continue;
                }

                ProcessCsvImportJob::dispatch(
                    $uploadId,
                    $csvPath,
                    $originalName, // keep original name for UI/progress
                    $processorClass,
                    count($csvFiles)
                );

                $dispatched++;
            }

            Log::channel($this->traceChannel)->info('upload: dispatch complete', [
                'upload_id' => $uploadId,
                'dispatched_jobs' => $dispatched,
                'total_files' => count($csvFiles),
                'dir_listing_after_dispatch' => $this->safeDirList($storagePath),
            ]);

            return response()->json([
                'success' => true,
                'upload_id' => $uploadId,
                'total_files' => $dispatched
            ]);
        } catch (\Exception $e) {
            Log::channel($this->traceChannel)->error('upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    protected function uploadFromTemp(Request $request)
    {
        $tempId = $request->input('temp_id');
        $mappingsRaw = $request->input('mappings');
        $mappings = json_decode($mappingsRaw, true);

        $tempData = Cache::get("temp_zip_{$tempId}");
        if (!$tempData) {
            Log::channel($this->traceChannel)->warning('uploadFromTemp: temp expired', [
                'temp_id' => $tempId,
                'cache_key' => "temp_zip_{$tempId}",
            ]);
            return response()->json(['success' => false, 'message' => 'Temporary files expired'], 404);
        }

        try {
            $uploadId = uniqid('import_', true);
            $storagePath = storage_path("app/uploads/{$uploadId}");
            mkdir($storagePath, 0755, true);

            Log::channel($this->traceChannel)->info('uploadFromTemp: starting', [
                'temp_id' => $tempId,
                'upload_id' => $uploadId,
                'storage_path' => $storagePath,
                'temp_path' => $tempData['path'] ?? null,
                'temp_dir_listing' => isset($tempData['path']) ? $this->safeDirList($tempData['path']) : null,
                'temp_files_count' => isset($tempData['files']) && is_array($tempData['files']) ? count($tempData['files']) : null,
                'temp_files_basenames' => isset($tempData['files']) && is_array($tempData['files']) ? array_map('basename', $tempData['files']) : null,
                'mappings_raw_preview' => is_string($mappingsRaw) ? mb_substr($mappingsRaw, 0, 500) : null,
                'mappings_decoded_keys' => is_array($mappings) ? array_keys($mappings) : null,
            ]);

            // Move files from temp to upload directory
            $csvFiles = [];
            foreach ($tempData['files'] as $tempPath) {
                $filename = basename($tempPath);

                Log::channel($this->traceChannel)->info('uploadFromTemp: considering temp file', [
                    'temp_id' => $tempId,
                    'upload_id' => $uploadId,
                    'temp_full_path' => $tempPath,
                    'basename' => $filename,
                    'exists' => file_exists($tempPath),
                    'size' => file_exists($tempPath) ? filesize($tempPath) : null,
                ]);

                if (!file_exists($tempPath)) {
                    Log::channel($this->traceChannel)->warning('uploadFromTemp: temp file missing', [
                        'temp_id' => $tempId,
                        'upload_id' => $uploadId,
                        'path' => $tempPath
                    ]);
                    continue;
                }

                if (!isset($mappings[$filename])) {
                    Log::channel($this->traceChannel)->info('uploadFromTemp: file not mapped, skipping', [
                        'temp_id' => $tempId,
                        'upload_id' => $uploadId,
                        'file' => $filename
                    ]);
                    continue;
                }

                $newPath = $storagePath . '/' . $filename;

                $moved = @rename($tempPath, $newPath);

                Log::channel($this->traceChannel)->info('uploadFromTemp: moved temp file', [
                    'temp_id' => $tempId,
                    'upload_id' => $uploadId,
                    'from' => $tempPath,
                    'to' => $newPath,
                    'rename_result' => $moved,
                    'to_exists' => file_exists($newPath),
                    'dir_listing_storage_now' => $this->safeDirList($storagePath),
                ]);

                if ($moved) {
                    $csvFiles[$filename] = $newPath;
                }
            }

            // Cleanup temp directory
            if (is_dir($tempData['path'])) {
                Log::channel($this->traceChannel)->info('uploadFromTemp: deleting temp directory', [
                    'temp_id' => $tempId,
                    'upload_id' => $uploadId,
                    'temp_path' => $tempData['path'],
                    'dir_listing_before_delete' => $this->safeDirList($tempData['path']),
                ]);
                $this->deleteDirectory($tempData['path']);
            }

            Cache::forget("temp_zip_{$tempId}");

            if (empty($csvFiles)) {
                Log::channel($this->traceChannel)->warning('uploadFromTemp: no valid files after move', [
                    'temp_id' => $tempId,
                    'upload_id' => $uploadId,
                    'storage_path' => $storagePath,
                    'dir_listing_storage' => $this->safeDirList($storagePath),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No valid files found or no files mapped'
                ], 400);
            }

            // Initialize progress
            Cache::put("import_progress_{$uploadId}", [
                'status' => 'queued',
                'total_files' => count($csvFiles),
                'processed_files' => 0,
                'total_rows' => 0,
                'current_file' => null,
                'results' => [],
                'storage_path' => $storagePath,
                'started_at' => now()->toISOString()
            ], 3600);

            Log::channel($this->traceChannel)->info('uploadFromTemp: progress initialized', [
                'temp_id' => $tempId,
                'upload_id' => $uploadId,
                'cache_key' => "import_progress_{$uploadId}",
                'total_files' => count($csvFiles),
                'storage_path' => $storagePath,
                'dir_listing_before_dispatch' => $this->safeDirList($storagePath),
            ]);

            // Dispatch jobs
            $dispatchedCount = 0;
            foreach ($csvFiles as $filename => $csvPath) {
                $processorKey = $mappings[$filename] ?? null;

                if (!$processorKey || !isset($this->processorMap[$processorKey])) {
                    Log::channel($this->traceChannel)->warning('uploadFromTemp: invalid processor mapping', [
                        'temp_id' => $tempId,
                        'upload_id' => $uploadId,
                        'file' => $filename,
                        'processor_key' => $processorKey,
                    ]);
                    continue;
                }

                $processorClass = $this->processorMap[$processorKey];

                Log::channel($this->traceChannel)->info('uploadFromTemp: dispatching', [
                    'temp_id' => $tempId,
                    'upload_id' => $uploadId,
                    'filename' => $filename,
                    'csv_path' => $csvPath,
                    'csv_path_exists' => file_exists($csvPath),
                    'processor_key' => $processorKey,
                    'processor_class' => $processorClass,
                ]);

                ProcessCsvImportJob::dispatch(
                    $uploadId,
                    $csvPath,
                    $filename,
                    $processorClass,
                    count($csvFiles)
                );

                $dispatchedCount++;
            }

            Log::channel($this->traceChannel)->info('uploadFromTemp: dispatch complete', [
                'temp_id' => $tempId,
                'upload_id' => $uploadId,
                'dispatched_jobs' => $dispatchedCount,
                'total_files' => count($csvFiles),
                'dir_listing_after_dispatch' => $this->safeDirList($storagePath),
            ]);

            if ($dispatchedCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to dispatch jobs. Check logs.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'upload_id' => $uploadId,
                'total_files' => $dispatchedCount
            ]);
        } catch (\Exception $e) {
            Log::channel($this->traceChannel)->error('uploadFromTemp failed', [
                'temp_id' => $tempId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function progress(string $uploadId)
    {
        $progress = Cache::get("import_progress_{$uploadId}");
        return $progress
            ? response()->json(['success' => true, 'progress' => $progress])
            : response()->json(['success' => false, 'message' => 'Not found'], 404);
    }

    public function reaggregate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'required|in:hourly,daily,weekly,monthly,quarterly,yearly,all'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $aggregationId = uniqid('agg_', true);

            Cache::put("agg_progress_{$aggregationId}", [
                'status' => 'queued',
                'processed' => 0,
                'total' => 0,
                'started_at' => now()->toISOString()
            ], 3600);

            ProcessAggregationJob::dispatch(
                $aggregationId,
                $request->input('start_date'),
                $request->input('end_date'),
                $request->input('type')
            );

            return response()->json(['success' => true, 'aggregation_id' => $aggregationId]);
        } catch (\Exception $e) {
            Log::channel($this->traceChannel)->error('reaggregate failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function aggregationProgress(string $aggregationId)
    {
        $progress = Cache::get("agg_progress_{$aggregationId}");
        return $progress
            ? response()->json(['success' => true, 'progress' => $progress])
            : response()->json(['success' => false, 'message' => 'Not found'], 404);
    }

    protected function findCsvFiles(string $directory): array
    {
        $csvFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'csv') {
                $csvFiles[] = $file->getPathname();
            }
        }
        return $csvFiles;
    }

    protected function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) return;

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

    /**
     * Safe directory listing for logs (keeps logs readable)
     */
    private function safeDirList(string $dir): ?array
    {
        try {
            if (!is_dir($dir)) return null;
            $items = array_values(array_diff(scandir($dir), ['.', '..']));
            // Avoid megabyte logs if someone drops tons of files
            return array_slice($items, 0, 200);
        } catch (\Throwable $e) {
            return ['<dir_list_failed>' => $e->getMessage()];
        }
    }
}
