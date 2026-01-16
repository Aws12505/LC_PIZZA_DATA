<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessCsvImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Send job logs to this dedicated file/channel
    private string $traceChannel = 'csv_import_trace';

    public $timeout = 3600; // 1 hour
    public $tries = 3;

    protected string $uploadId;
    protected string $csvPath;
    protected string $filename;
    protected string $processorClass;
    protected int $totalFiles;

    public function __construct(
        string $uploadId,
        string $csvPath,
        string $filename,
        string $processorClass,
        int $totalFiles
    ) {
        $this->uploadId = $uploadId;
        $this->csvPath = $csvPath;
        $this->filename = $filename;
        $this->processorClass = $processorClass;
        $this->totalFiles = $totalFiles;
    }

    public function handle()
    {
        ini_set('memory_limit', '2G');
        $startTime = microtime(true);

        $succeeded = false;

        // Snapshot at job start (helps catch any rename/missing-file issues)
        $this->trace('job_start', [
            'attempt' => method_exists($this, 'attempts') ? $this->attempts() : null,
            'upload_id' => $this->uploadId,
            'filename' => $this->filename,
            'csv_path' => $this->csvPath,
            'csv_exists' => file_exists($this->csvPath),
            'dir' => dirname($this->csvPath),
            'dir_listing' => $this->safeDirList(dirname($this->csvPath)),
            'processor_class' => $this->processorClass,
        ]);

        try {
            if (!file_exists($this->csvPath)) {
                throw new \Exception("CSV file not found (before processing): {$this->csvPath}");
            }

            $this->updateProgress('processing');

            $processor = new $this->processorClass();
            $result = $this->streamCsvOptimized($processor);

            $duration = round(microtime(true) - $startTime, 2);
            $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

            $this->updateProgress('completed', [
                'rows' => $result['rows'],
                'dates' => $result['dates'],
                'duration' => $duration,
                'memory_mb' => $memoryPeak
            ]);

            $this->trace('job_completed', [
                'rows' => $result['rows'],
                'dates_count' => count($result['dates'] ?? []),
                'duration_sec' => $duration,
                'memory_peak_mb' => $memoryPeak,
            ]);

            $succeeded = true;
        } catch (\Exception $e) {
            $this->updateProgress('failed', ['error' => $e->getMessage()]);

            // Log to BOTH: default laravel log + dedicated trace
            Log::error("Import job failed", [
                'upload_id' => $this->uploadId,
                'file' => $this->filename,
                'csv_path' => $this->csvPath,
                'attempt' => method_exists($this, 'attempts') ? $this->attempts() : null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->trace('job_failed', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'attempt' => method_exists($this, 'attempts') ? $this->attempts() : null,
                'csv_exists_now' => file_exists($this->csvPath),
                'dir_listing_now' => $this->safeDirList(dirname($this->csvPath)),
            ], 'error');

            throw $e;
        } finally {
            $attempt = method_exists($this, 'attempts') ? $this->attempts() : 1;
            $finalAttempt = ($attempt >= $this->tries);

            // Only delete CSV if succeeded OR final attempt (no more retries)
            if ($succeeded || $finalAttempt) {
                if (file_exists($this->csvPath)) {
                    @unlink($this->csvPath);
                    $this->trace('cleanup_deleted_csv', [
                        'csv_path' => $this->csvPath,
                        'deleted' => true,
                        'reason' => $succeeded ? 'succeeded' : 'final_attempt',
                    ]);
                } else {
                    $this->trace('cleanup_deleted_csv', [
                        'csv_path' => $this->csvPath,
                        'deleted' => false,
                        'reason' => 'file_missing_at_cleanup',
                        'dir_listing_now' => $this->safeDirList(dirname($this->csvPath)),
                    ], 'warning');
                }
            } else {
                $this->trace('cleanup_skipped_csv_delete', [
                    'csv_path' => $this->csvPath,
                    'reason' => 'will_retry',
                    'attempt' => $attempt,
                    'tries' => $this->tries,
                ]);
            }

            $this->cleanupIfComplete();
        }
    }

    protected function streamCsvOptimized($processor): array
    {
        // Snapshot right before opening the file (helps pinpoint “rename” timing)
        $this->trace('pre_fopen', [
            'csv_path' => $this->csvPath,
            'csv_exists' => file_exists($this->csvPath),
            'dir_listing' => $this->safeDirList(dirname($this->csvPath)),
        ]);

        $handle = fopen($this->csvPath, 'r');
        if ($handle === false) {
            throw new \Exception("Cannot open CSV file: {$this->csvPath}");
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new \Exception("CSV file has no headers");
        }

        $headers = array_map(function ($header) {
            $normalized = strtolower(trim($header));
            $normalized = str_replace([' ', '-', '.'], '_', $normalized);
            return preg_replace('/[^a-z0-9_]/', '', $normalized);
        }, $headers);

        $this->trace('headers_normalized', [
            'header_count' => count($headers),
            'headers' => array_slice($headers, 0, 80),
        ]);

        $chunkSize = 500;
        $chunk = [];
        $totalRows = 0;
        $detectedDates = [];
        $rowNumber = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($row) !== count($headers)) {
                Log::warning("Row column count mismatch", [
                    'file' => $this->filename,
                    'row' => $rowNumber,
                    'expected' => count($headers),
                    'actual' => count($row)
                ]);
                continue;
            }

            $rowData = array_combine($headers, $row);

            // --- FIX: normalize business_date if present (prevents MySQL DATE errors like 12/9/2025)
            if (isset($rowData['business_date']) && $rowData['business_date'] !== '' && $rowData['business_date'] !== null) {
                $rowData['business_date'] = $this->normalizeBusinessDate($rowData['business_date'], $rowNumber);
            }

            $chunk[] = $rowData;

            if (count($chunk) >= $chunkSize) {
                $processed = $this->processChunk($chunk, $processor, $detectedDates);
                $totalRows += $processed;

                $this->updateProgress('processing', ['processed_rows' => $totalRows]);

                $chunk = [];

                if ($rowNumber % 5000 === 0) {
                    gc_collect_cycles();
                    $this->trace('gc', ['rowNumber' => $rowNumber, 'totalRows' => $totalRows]);
                }
            }
        }

        if (!empty($chunk)) {
            $processed = $this->processChunk($chunk, $processor, $detectedDates);
            $totalRows += $processed;
        }

        fclose($handle);
        sort($detectedDates);

        return [
            'rows' => $totalRows,
            'dates' => $detectedDates
        ];
    }

    /**
     * Convert any incoming business_date format to YYYY-MM-DD (or null if impossible)
     */
    private function normalizeBusinessDate($value, int $rowNumber): ?string
    {
        $raw = trim((string)$value);

        // Common format from your error: 12/9/2025
        // Carbon can parse this, but we’ll log failures.
        try {
            $normalized = Carbon::parse($raw)->toDateString();
            return $normalized;
        } catch (\Exception $e) {
            $this->trace('business_date_parse_failed', [
                'row' => $rowNumber,
                'raw_value' => $raw,
                'error' => $e->getMessage(),
            ], 'warning');

            // Returning null may still fail if DB column is NOT NULL / part of unique keys.
            // If you want to hard-fail instead, throw here.
            return null;
        }
    }

    protected function processChunk(array $chunk, $processor, array &$detectedDates): int
    {
        $grouped = $this->groupByDate($chunk);

        $processed = 0;
        foreach ($grouped as $date => $dateData) {
            $processor->process($dateData, $date);
            $processed += count($dateData);

            if (!in_array($date, $detectedDates)) {
                $detectedDates[] = $date;
            }
        }

        return $processed;
    }

    protected function groupByDate(array $chunk): array
    {
        $grouped = [];
        foreach ($chunk as $row) {
            $date = $this->extractDate($row);
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            $grouped[$date][] = $row;
        }
        return $grouped;
    }

    protected function extractDate(array $row): string
    {
        // Prefer already-normalized business_date if present
        if (!empty($row['business_date'])) {
            try {
                return Carbon::parse($row['business_date'])->toDateString();
            } catch (\Exception $e) {
                // fall through
            }
        }

        foreach ($row as $key => $value) {
            if (stripos($key, 'date') !== false && !empty($value)) {
                try {
                    return Carbon::parse($value)->toDateString();
                } catch (\Exception $e) {
                    // Continue searching
                }
            }
        }

        return Carbon::today()->toDateString();
    }

    protected function updateProgress(string $status, array $data = [])
    {
        $progress = Cache::get("import_progress_{$this->uploadId}", [
            'status' => 'processing',
            'total_files' => $this->totalFiles,
            'processed_files' => 0,
            'total_rows' => 0,
            'current_file' => null,
            'results' => [],
            'storage_path' => null
        ]);

        // Keep status updated (optional but recommended)
        $progress['status'] = $status;
        $progress['current_file'] = $this->filename;

        if ($status === 'completed') {
            $progress['processed_files']++;
            $progress['total_rows'] += $data['rows'] ?? 0;
            $progress['results'][] = [
                'file' => $this->filename,
                'status' => 'success',
                'rows' => $data['rows'] ?? 0,
                'dates' => $data['dates'] ?? [],
                'duration' => $data['duration'] ?? 0
            ];

            if ($progress['processed_files'] >= $progress['total_files']) {
                $progress['status'] = 'completed';
            }
        }

        if ($status === 'failed') {
            $progress['results'][] = [
                'file' => $this->filename,
                'status' => 'failed',
                'error' => $data['error'] ?? 'Unknown error'
            ];
        }

        if (isset($data['processed_rows'])) {
            $progress['processed_rows'] = $data['processed_rows'];
        }

        $progress['updated_at'] = now()->toISOString();

        Cache::put("import_progress_{$this->uploadId}", $progress, 3600);
    }

    protected function cleanupIfComplete(): void
    {
        $progress = Cache::get("import_progress_{$this->uploadId}");

        if (!$progress || !isset($progress['storage_path'])) {
            return;
        }

        if ($progress['processed_files'] >= $progress['total_files']) {
            $storagePath = $progress['storage_path'];

            $this->trace('cleanupIfComplete', [
                'upload_id' => $this->uploadId,
                'storage_path' => $storagePath,
                'dir_listing_before_delete' => $this->safeDirList($storagePath),
            ]);

            if (is_dir($storagePath)) {
                $this->deleteDirectory($storagePath);
            }
        }
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

    private function trace(string $event, array $context = [], string $level = 'info'): void
    {
        $payload = array_merge([
            'event' => $event,
            'upload_id' => $this->uploadId ?? null,
            'filename' => $this->filename ?? null,
        ], $context);

        if ($level === 'error') {
            Log::channel($this->traceChannel)->error($event, $payload);
        } elseif ($level === 'warning') {
            Log::channel($this->traceChannel)->warning($event, $payload);
        } else {
            Log::channel($this->traceChannel)->info($event, $payload);
        }
    }

    private function safeDirList(string $dir): ?array
    {
        try {
            if (!is_dir($dir)) return null;
            $items = array_values(array_diff(scandir($dir), ['.', '..']));
            return array_slice($items, 0, 200);
        } catch (\Throwable $e) {
            return ['<dir_list_failed>' => $e->getMessage()];
        }
    }
}
