<?php

namespace App\Console\Commands\Import;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CleanupTempUploadsCommand extends Command
{
    protected $signature = 'uploads:cleanup-temp';
    protected $description = 'Clean up temporary upload files (temp and abandoned uploads)';

    public function handle()
    {
        $cleaned = 0;
        $cutoff = now()->subMinutes(15)->timestamp; // 15 minutes old

        // Clean temp directory (ZIP extractions)
        $cleaned += $this->cleanTempDirectory($cutoff);
        
        // Clean abandoned upload directories (not processed)
        $cleaned += $this->cleanAbandonedUploads($cutoff);

        $this->info("✓ Cleaned {$cleaned} directories");
        Log::info("Temp upload cleanup completed", ['cleaned' => $cleaned]);
        
        return 0;
    }

    protected function cleanTempDirectory(int $cutoff): int
    {
        $tempPath = storage_path('app/temp');
        
        if (!is_dir($tempPath)) {
            return 0;
        }

        $cleaned = 0;
        $directories = glob($tempPath . '/temp_*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $tempId = basename($dir);
            
            // Check age
            if (filemtime($dir) < $cutoff) {
                // Check if still in cache (being used)
                if (!Cache::has("temp_zip_{$tempId}")) {
                    $this->deleteDirectory($dir);
                    $cleaned++;
                    $this->info("  Cleaned temp: {$tempId}");
                }
            }
        }

        return $cleaned;
    }

    protected function cleanAbandonedUploads(int $cutoff): int
    {
        $uploadPath = storage_path('app/uploads');
        
        if (!is_dir($uploadPath)) {
            return 0;
        }

        $cleaned = 0;
        $directories = glob($uploadPath . '/import_*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $uploadId = basename($dir);
            
            // Check age
            if (filemtime($dir) < $cutoff) {
                $progress = Cache::get("import_progress_{$uploadId}");
                
                // Delete if:
                // 1. No progress data (abandoned before upload)
                // 2. Status is 'completed' (already processed, can cleanup)
                if (!$progress || $progress['status'] === 'completed') {
                    $this->deleteDirectory($dir);
                    Cache::forget("import_progress_{$uploadId}");
                    $cleaned++;
                    $this->info("  Cleaned upload: {$uploadId}");
                }
            }
        }

        return $cleaned;
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
}
