<?php

namespace App\Services\Database;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Log;

/**
 * DatabaseRouter - Smart query routing between operational and analytics databases
 *
 * Supports nullable date ranges:
 * - startDate = null AND endDate = null  => all data (hot + archive UNION)
 * - startDate only => from startDate to today
 * - endDate only   => from earliest to endDate
 *
 * Hot/archive routing is preserved.
 */
class DatabaseRouter
{
    /**
     * Hot data retention period in days
     */
    protected const HOT_DATA_DAYS = 90;

    /**
     * Get cutoff date
     */
    protected static function cutoff(): Carbon
    {
        return Carbon::now()->subDays(self::HOT_DATA_DAYS);
    }

    /**
     * Create a query builder that automatically routes to correct database(s)
     *
     * @param string $baseTable
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return Builder
     */
    public static function query(
        string $baseTable,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Builder {
        $cutoff = self::cutoff();

        // Normalize open-ended ranges
        if ($startDate && !$endDate) {
            $endDate = Carbon::now();
        }

        if (!$startDate && $endDate) {
            // Arbitrary "old enough" date
            $startDate = Carbon::create(2000, 1, 1);
        }

        /**
         * CASE 0: No dates at all → UNION ALL (hot + archive)
         */
        if (!$startDate && !$endDate) {
            Log::debug('Routing ALL data (no date filter)', [
                'table' => $baseTable,
            ]);

            $archive = DB::connection('analytics')
                ->table("{$baseTable}_archive");

            $hot = DB::connection('operational')
                ->table("{$baseTable}_hot");

            return $archive->unionAll($hot);
        }

        /**
         * CASE 1: Entire range in ARCHIVE
         */
        if ($endDate < $cutoff) {
            Log::debug('Routing to archive only', [
                'table' => $baseTable,
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ]);

            return DB::connection('analytics')
                ->table("{$baseTable}_archive")
                ->whereBetween('business_date', [
                    $startDate->toDateString(),
                    $endDate->toDateString(),
                ]);
        }

        /**
         * CASE 2: Entire range in HOT
         */
        if ($startDate >= $cutoff) {
            Log::debug('Routing to operational only', [
                'table' => $baseTable,
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ]);

            return DB::connection('operational')
                ->table("{$baseTable}_hot")
                ->whereBetween('business_date', [
                    $startDate->toDateString(),
                    $endDate->toDateString(),
                ]);
        }

        /**
         * CASE 3: Spans both → UNION
         */
        Log::debug('Routing to UNION (spans hot + archive)', [
            'table'  => $baseTable,
            'start'  => $startDate->toDateString(),
            'end'    => $endDate->toDateString(),
            'cutoff' => $cutoff->toDateString(),
        ]);

        $archiveQuery = DB::connection('analytics')
            ->table("{$baseTable}_archive")
            ->whereBetween('business_date', [
                $startDate->toDateString(),
                $cutoff->copy()->subDay()->toDateString(),
            ]);

        $hotQuery = DB::connection('operational')
            ->table("{$baseTable}_hot")
            ->whereBetween('business_date', [
                $cutoff->toDateString(),
                $endDate->toDateString(),
            ]);

        return $archiveQuery->unionAll($hotQuery);
    }

    /**
     * Check if a date range spans both databases
     */
    public static function spansDatabase(Carbon $startDate, Carbon $endDate): bool
    {
        $cutoff = self::cutoff();
        return $startDate < $cutoff && $endDate >= $cutoff;
    }

    /**
     * Get cutoff date between hot and archive data
     */
    public static function getCutoffDate(): Carbon
    {
        return self::cutoff();
    }

    /**
     * Get statistics about data distribution across databases
     */
    public static function getDataDistribution(string $baseTable): array
    {
        $cutoff = self::cutoff();

        $hotCount = DB::connection('operational')
            ->table("{$baseTable}_hot")
            ->count();

        $archiveCount = DB::connection('analytics')
            ->table("{$baseTable}_archive")
            ->count();

        $totalRows = $hotCount + $archiveCount;

        return [
            'table'               => $baseTable,
            'hot_rows'            => $hotCount,
            'archive_rows'        => $archiveCount,
            'total_rows'          => $totalRows,
            'cutoff_date'         => $cutoff->toDateString(),
            'hot_percentage'      => $totalRows > 0 ? round(($hotCount / $totalRows) * 100, 2) : 0,
            'archive_percentage'  => $totalRows > 0 ? round(($archiveCount / $totalRows) * 100, 2) : 0,
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
