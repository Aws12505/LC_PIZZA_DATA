<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Archive a single table's date range in background
 */
class ArchiveDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour per batch
    public $tries = 3;
    public $backoff = 300; // 5 min retry delay

    protected string $archiveId;
    protected string $table;
    protected Carbon $startDate;
    protected Carbon $endDate;
    protected int $totalTables;
    protected bool $verify;

    public function __construct(
        string $archiveId,
        string $table,
        Carbon $startDate,
        Carbon $endDate,
        int $totalTables,
        bool $verify = false
    ) {
        $this->archiveId = $archiveId;
        $this->table = $table;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->totalTables = $totalTables;
        $this->verify = $verify;
    }

    public function handle(): void
    {
        Log::info("Archive job started", [
            'archive_id' => $this->archiveId,
            'table' => $this->table,
            'start' => $this->startDate->toDateString(),
            'end' => $this->endDate->toDateString()
        ]);

        try {
            $hotTable = "{$this->table}_hot";
            $archiveTable = "{$this->table}_archive";

            // Count rows to archive (from operational DB)
            $count = DB::connection('operational')
                ->table($hotTable)
                ->whereBetween('business_date', [
                    $this->startDate->toDateString(),
                    $this->endDate->toDateString()
                ])
                ->count();

            if ($count === 0) {
                $this->updateProgress('skipped', 0);
                return;
            }

            // Get the actual database names from config
            $operationalDb = config('database.connections.operational.database');

            DB::transaction(function() use ($hotTable, $archiveTable, $operationalDb) {
                // Archive using INSERT...SELECT with proper database name
                DB::connection('analytics')->statement("
                    INSERT IGNORE INTO {$archiveTable}
                    SELECT * FROM {$operationalDb}.{$hotTable}
                    WHERE business_date BETWEEN ? AND ?
                ", [
                    $this->startDate->toDateString(),
                    $this->endDate->toDateString()
                ]);

                // Delete from operational
                DB::connection('operational')
                    ->table($hotTable)
                    ->whereBetween('business_date', [
                        $this->startDate->toDateString(),
                        $this->endDate->toDateString()
                    ])
                    ->delete();
            });

            // Verification
            if ($this->verify) {
                $remaining = DB::connection('operational')
                    ->table($hotTable)
                    ->whereBetween('business_date', [
                        $this->startDate->toDateString(),
                        $this->endDate->toDateString()
                    ])
                    ->count();

                if ($remaining > 0) {
                    throw new \Exception("Verification failed: {$remaining} rows still in operational DB");
                }
            }

            $this->updateProgress('completed', $count);

            Log::info("Archive job completed", [
                'archive_id' => $this->archiveId,
                'table' => $this->table,
                'rows' => $count
            ]);

        } catch (\Exception $e) {
            $this->updateProgress('failed', 0, $e->getMessage());

            Log::error("Archive job failed", [
                'archive_id' => $this->archiveId,
                'table' => $this->table,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    protected function updateProgress(string $status, int $rowsArchived, ?string $error = null): void
    {
        $progress = Cache::get("archive_progress_{$this->archiveId}", [
            'status' => 'running',
            'total_tables' => $this->totalTables,
            'completed_tables' => 0,
            'total_rows' => 0,
            'results' => [],
            'started_at' => now()->toISOString()
        ]);

        $progress['results'][$this->table] = [
            'status' => $status,
            'rows_archived' => $rowsArchived,
            'start_date' => $this->startDate->toDateString(),
            'end_date' => $this->endDate->toDateString(),
            'error' => $error,
            'completed_at' => now()->toISOString()
        ];

        $progress['completed_tables']++;
        $progress['total_rows'] += $rowsArchived;

        if ($progress['completed_tables'] >= $progress['total_tables']) {
            $progress['status'] = 'completed';
            $progress['finished_at'] = now()->toISOString();
        }

        Cache::put("archive_progress_{$this->archiveId}", $progress, 7200);
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateProgress('failed', 0, $exception->getMessage());

        Log::error("Archive job failed permanently", [
            'archive_id' => $this->archiveId,
            'table' => $this->table,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);
    }
}
