<?php

namespace App\Services\Database;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class DatabaseRouter
{
    protected const HOT_DATA_DAYS = 90;

    public static function getCutoffDate(): Carbon
    {
        return Carbon::now()->subDays(self::HOT_DATA_DAYS);
    }

    protected static function tableExists(string $connection, string $table): bool
    {
        try {
            return Schema::connection($connection)->hasTable($table);
        } catch (\Throwable $e) {
            @error_log("Schema check failed for {$connection}.{$table}: " . $e->getMessage());
            return false;
        }
    }

    public static function archiveQuery(string $baseTable): ?Builder
    {
        $table = "{$baseTable}_archive";
        if (!self::tableExists('analytics', $table)) {
            return null;
        }
        return DB::connection('analytics')->table($table);
    }

    public static function hotQuery(string $baseTable): ?Builder
    {
        $table = "{$baseTable}_hot";
        if (!self::tableExists('operational', $table)) {
            return null;
        }
        return DB::connection('operational')->table($table);
    }

    /**
     * Returns 1 or 2 Builders (hot/archive) WITHOUT unioning across connections.
     * Dates are nullable:
     * - start=null & end=null => both queries, no date filter
     * - start only => start..today
     * - end only => 2000-01-01..end
     *
     * @return Builder[]
     */
    public static function routedQueries(string $baseTable, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $cutoff = self::getCutoffDate();

        // Normalize open ended ranges
        if ($startDate && !$endDate) {
            $endDate = Carbon::now();
        }
        if (!$startDate && $endDate) {
            $startDate = Carbon::create(2000, 1, 1);
        }

        // CASE 0: no dates -> both, no date filter
        if (!$startDate && !$endDate) {
            Log::debug("Routing (no dates): archive + hot", ['table' => $baseTable]);

            $queries = array_values(array_filter([
                self::archiveQuery($baseTable),
                self::hotQuery($baseTable),
            ]));

            if (empty($queries)) {
                throw new \RuntimeException("No tables found for {$baseTable} (missing *_hot and *_archive).");
            }

            return $queries;
        }

        // CASE 1: archive only
        if ($endDate < $cutoff) {
            Log::debug("Routing: archive only", [
                'table' => $baseTable,
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ]);

            $q = self::archiveQuery($baseTable);
            if (!$q) {
                throw new \RuntimeException("Missing analytics table: {$baseTable}_archive");
            }

            return [
                $q->whereBetween('business_date', [$startDate->toDateString(), $endDate->toDateString()])
            ];
        }

        // CASE 2: hot only
        if ($startDate >= $cutoff) {
            Log::debug("Routing: hot only", [
                'table' => $baseTable,
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ]);

            $q = self::hotQuery($baseTable);
            if (!$q) {
                throw new \RuntimeException("Missing operational table: {$baseTable}_hot");
            }

            return [
                $q->whereBetween('business_date', [$startDate->toDateString(), $endDate->toDateString()])
            ];
        }

        // CASE 3: spans both
        Log::debug("Routing: spans archive + hot", [
            'table'  => $baseTable,
            'start'  => $startDate->toDateString(),
            'end'    => $endDate->toDateString(),
            'cutoff' => $cutoff->toDateString(),
        ]);

        $archive = self::archiveQuery($baseTable);
        $hot     = self::hotQuery($baseTable);

        $queries = [];

        if ($archive) {
            $queries[] = $archive->whereBetween('business_date', [
                $startDate->toDateString(),
                $cutoff->copy()->subDay()->toDateString(),
            ]);
        }

        if ($hot) {
            $queries[] = $hot->whereBetween('business_date', [
                $cutoff->toDateString(),
                $endDate->toDateString(),
            ]);
        }

        if (empty($queries)) {
            throw new \RuntimeException("No routed tables available for {$baseTable} in spanning range.");
        }

        return $queries;
    }
}
