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

/**
 * OPTIMIZED Import Job
 * - Streams CSV in 500-row chunks (matches YOUR BaseTableProcessor)
 * - Auto-detects dates from CSV data
 * - Uses YOUR existing processor->process() method
 * - CLEANS UP: Deletes CSV after processing, entire directory when all done
 */
class ProcessCsvImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        
        try {
            $this->updateProgress('processing');
            
            // OPTIMIZED: Stream CSV using YOUR pattern
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

            Log::info("Import job completed", [
                'upload_id' => $this->uploadId,
                'file' => $this->filename,
                'rows' => $result['rows'],
                'dates' => $result['dates'],
                'duration' => $duration,
                'memory_peak' => $memoryPeak . ' MB'
            ]);

        } catch (\Exception $e) {
            $this->updateProgress('failed', ['error' => $e->getMessage()]);
            
            Log::error("Import job failed", [
                'upload_id' => $this->uploadId,
                'file' => $this->filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;

        } finally {
            // CLEANUP: Delete CSV file after processing
            if (file_exists($this->csvPath)) {
                @unlink($this->csvPath);
                Log::debug("Deleted CSV file", ['file' => $this->csvPath]);
            }

            // Check if all files processed, then delete entire directory
            $this->cleanupIfComplete();
        }
    }

    /**
     * OPTIMIZED CSV STREAMING
     * - Never loads entire file into memory
     * - Processes in 500-row chunks (matches YOUR BaseTableProcessor::getChunkSize())
     * - Uses YOUR existing processor->process($data, $date) method
     */
    protected function streamCsvOptimized($processor): array
    {
        $handle = fopen($this->csvPath, 'r');
        if ($handle === false) {
            throw new \Exception("Cannot open CSV file: {$this->csvPath}");
        }

        // Read and normalize headers (same as YOUR LCReportDataService->readCsvFile)
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new \Exception("CSV file has no headers");
        }

        $headers = array_map(function($header) {
            $normalized = strtolower(trim($header));
            $normalized = str_replace([' ', '-', '.'], '_', $normalized);
            return preg_replace('/[^a-z0-9_]/', '', $normalized);
        }, $headers);

        $chunkSize = 500; // Matches YOUR BaseTableProcessor chunk size
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
            $chunk[] = $rowData;

            // Process chunk when it reaches chunk size
            if (count($chunk) >= $chunkSize) {
                $processed = $this->processChunk($chunk, $processor, $detectedDates);
                $totalRows += $processed;
                
                // Update real-time progress
                $this->updateProgress('processing', ['processed_rows' => $totalRows]);
                
                $chunk = []; // Clear chunk
                
                // Garbage collection every 5000 rows for memory optimization
                if ($rowNumber % 5000 === 0) {
                    gc_collect_cycles();
                }
            }
        }

        // Process remaining chunk
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
     * Process chunk by grouping by date and using YOUR processor->process()
     */
    protected function processChunk(array $chunk, $processor, array &$detectedDates): int
    {
        $grouped = $this->groupByDate($chunk);
        
        $processed = 0;
        foreach ($grouped as $date => $dateData) {
            // Use YOUR existing processor->process($data, $date) method
            $processor->process($dateData, $date);
            $processed += count($dateData);
            
            if (!in_array($date, $detectedDates)) {
                $detectedDates[] = $date;
            }
        }
        
        return $processed;
    }

    /**
     * Group rows by detected date
     */
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

    /**
     * Extract date from row data (auto-detect)
     */
    protected function extractDate(array $row): string
    {
        foreach ($row as $key => $value) {
            if (stripos($key, 'date') !== false && !empty($value)) {
                try {
                    return Carbon::parse($value)->toDateString();
                } catch (\Exception $e) {
                    // Continue searching
                }
            }
        }
        // Fallback to today
        return Carbon::today()->toDateString();
    }

    /**
     * Update progress in cache
     */
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

    /**
     * CLEANUP: Delete entire upload directory if all files processed
     * Same pattern as YOUR LCReportDataService finally block
     */
    protected function cleanupIfComplete(): void
    {
        $progress = Cache::get("import_progress_{$this->uploadId}");
        
        if (!$progress || !isset($progress['storage_path'])) {
            return;
        }

        // Check if all files processed
        if ($progress['processed_files'] >= $progress['total_files']) {
            $storagePath = $progress['storage_path'];
            
            if (is_dir($storagePath)) {
                $this->deleteDirectory($storagePath);
                Log::info("Deleted upload directory", ['path' => $storagePath]);
            }
        }
    }

    /**
     * Delete directory recursively - same as YOUR PureIO->deleteDirectory
     */
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
}
