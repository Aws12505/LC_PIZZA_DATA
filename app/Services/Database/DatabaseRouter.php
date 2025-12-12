<?php

namespace App\Services\Database;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Log;

/**
 * DatabaseRouter - Smart query routing between operational and analytics databases
 *
 * IMPORTANT:
 * - We DO NOT UNION across different connections. Laravel cannot reliably union
 *   queries from different connections, and many databases cannot union across
 *   servers anyway.
 *
 * Instead we return one or two Builders, and the caller streams them sequentially.
 */
class DatabaseRouter
{
    /**
     * Hot data retention period in days
     */
    protected const HOT_DATA_DAYS = 90;

    /**
     * Get cutoff date between hot and archive data
     */
    public static function getCutoffDate(): Carbon
    {
        return Carbon::now()->subDays(self::HOT_DATA_DAYS);
    }

    /**
     * Build base archive query (no filters)
     */
    public static function archiveQuery(string $baseTable): Builder
    {
        return DB::connection('analytics')->table("{$baseTable}_archive");
    }

    /**
     * Build base hot query (no filters)
     */
    public static function hotQuery(string $baseTable): Builder
    {
        return DB::connection('operational')->table("{$baseTable}_hot");
    }

    /**
     * Return the routed queries for the table and date range.
     *
     * Nullable date behavior:
     * - start=null & end=null  => BOTH queries, no date filter
     * - start only             => start..today
     * - end only               => very-old..end
     *
     * @return Builder[] array of 1 or 2 query builders
     */
    public static function routedQueries(
        string $baseTable,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $cutoff = self::getCutoffDate();

        // Normalize open-ended ranges
        if ($startDate && !$endDate) {
            $endDate = Carbon::now();
        }
        if (!$startDate && $endDate) {
            // pick a very old date; adjust if your data starts later
            $startDate = Carbon::create(2000, 1, 1);
        }

        // Case 0: no dates => both, no date filtering
        if (!$startDate && !$endDate) {
            Log::debug("Routing (no dates): archive + hot", ['table' => $baseTable]);

            return [
                self::archiveQuery($baseTable),
                self::hotQuery($baseTable),
            ];
        }

        // Case 1: archive only
        if ($endDate < $cutoff) {
            Log::debug("Routing: archive only", [
                'table' => $baseTable,
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ]);

            return [
                self::archiveQuery($baseTable)
                    ->whereBetween('business_date', [$startDate->toDateString(), $endDate->toDateString()]),
            ];
        }

        // Case 2: hot only
        if ($startDate >= $cutoff) {
            Log::debug("Routing: hot only", [
                'table' => $baseTable,
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ]);

            return [
                self::hotQuery($baseTable)
                    ->whereBetween('business_date', [$startDate->toDateString(), $endDate->toDateString()]),
            ];
        }

        // Case 3: spans both
        Log::debug("Routing: spans archive + hot", [
            'table'  => $baseTable,
            'start'  => $startDate->toDateString(),
            'end'    => $endDate->toDateString(),
            'cutoff' => $cutoff->toDateString(),
        ]);

        return [
            self::archiveQuery($baseTable)
                ->whereBetween('business_date', [
                    $startDate->toDateString(),
                    $cutoff->copy()->subDay()->toDateString(),
                ]),
            self::hotQuery($baseTable)
                ->whereBetween('business_date', [
                    $cutoff->toDateString(),
                    $endDate->toDateString(),
                ]),
        ];
    }

    /**
     * Backwards-compatible API:
     * Returns a SINGLE Builder only if the route resolves to one query.
     * If it would require both, we throw with a clear message.
     *
     * Prefer routedQueries() in all new code.
     */
    public static function query(string $baseTable, ?Carbon $startDate = null, ?Carbon $endDate = null): Builder
    {
        $queries = self::routedQueries($baseTable, $startDate, $endDate);

        if (count($queries) === 1) {
            return $queries[0];
        }

        throw new \RuntimeException(
            "DatabaseRouter::query() cannot return a single Builder for spanning/no-date ranges. Use routedQueries()."
        );
    }

    /**
     * Check if a date range spans both databases
     */
    public static function spansDatabase(Carbon $startDate, Carbon $endDate): bool
    {
        $cutoff = self::getCutoffDate();
        return $startDate < $cutoff && $endDate >= $cutoff;
    }

    /**
     * Get statistics about data distribution across databases
     */
    public static function getDataDistribution(string $baseTable): array
    {
        $cutoff = self::getCutoffDate();

        $hotCount = DB::connection('operational')
            ->table("{$baseTable}_hot")
            ->count();

        $archiveCount = DB::connection('analytics')
            ->table("{$baseTable}_archive")
            ->count();

        $totalRows = $hotCount + $archiveCount;

        return [
            'table'              => $baseTable,
            'hot_rows'           => $hotCount,
            'archive_rows'       => $archiveCount,
            'total_rows'         => $totalRows,
            'cutoff_date'        => $cutoff->toDateString(),
            'hot_percentage'     => $totalRows > 0 ? round(($hotCount / $totalRows) * 100, 2) : 0,
            'archive_percentage' => $totalRows > 0 ? round(($archiveCount / $totalRows) * 100, 2) : 0,
        ];
    }

    /**
     * Get data distribution for all tables
     */
    public static function getAllDataDistribution(): array
    {
        $tables = [
            'detail_orders', 'order_line', 'summary_sales', 'summary_items',
            'summary_transactions', 'waste', 'cash_management', 'financial_views',
            'finance_data', 'final_summaries', 'hourly_sales'
        ];

        $stats = [];
        foreach ($tables as $table) {
            $stats[$table] = self::getDataDistribution($table);
        }

        return $stats;
    }
}
