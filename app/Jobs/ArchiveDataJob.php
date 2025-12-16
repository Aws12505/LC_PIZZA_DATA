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
    public $backoff = 100; // 5 min retry delay

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

            // Count rows to archive
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

            // Get the actual database names
            $operationalDb = config('database.connections.operational.database');

            // Get column list (excluding generated columns)
            $columns = $this->getInsertableColumns($hotTable);

            DB::transaction(function() use ($hotTable, $archiveTable, $operationalDb, $columns) {
                // Archive using INSERT...SELECT with explicit column list
                DB::connection('analytics')->statement("
                    INSERT IGNORE INTO {$archiveTable} ({$columns})
                    SELECT {$columns} FROM {$operationalDb}.{$hotTable}
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

    /**
     * Get list of insertable columns (excluding generated columns)
     */
    protected function getInsertableColumns(string $table): string
    {
        $columns = DB::connection('operational')
            ->select("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND EXTRA NOT LIKE '%GENERATED%'
                AND EXTRA NOT LIKE '%VIRTUAL%'
                ORDER BY ORDINAL_POSITION
            ", [$table]);

        return implode(', ', array_map(fn($col) => $col->COLUMN_NAME, $columns));
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
