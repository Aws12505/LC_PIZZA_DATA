<?php

namespace App\Services\Database;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Log;

/**
 * DatabaseRouter - Smart query routing between operational and analytics databases
 * 
 * Automatically routes queries based on date ranges:
 * - Last 90 days: operational database (hot tables)
 * - 91+ days: analytics database (archive tables)
 * - Spanning queries: UNION across both databases
 * 
 * Usage:
 * $orders = DatabaseRouter::query('detail_orders', $startDate, $endDate)
 *     ->where('franchise_store', 'STORE001')
 *     ->get();
 */
class DatabaseRouter
{
    /**
     * Hot data retention period in days
     */
    protected const HOT_DATA_DAYS = 90;

    /**
     * Get the appropriate database connection for a given date
     * 
     * @param Carbon $date Date to check
     * @return string 'operational' or 'analytics'
     */
    public static function getDatabase(Carbon $date): string
    {
        $cutoff = Carbon::now()->subDays(self::HOT_DATA_DAYS);
        return $date >= $cutoff ? 'operational' : 'analytics';
    }

    /**
     * Get the appropriate table name for a given base table and date
     * 
     * @param string $baseTable Base table name (without suffix)
     * @param Carbon $date Date to check
     * @return string Full table name with suffix
     */
    public static function getTableName(string $baseTable, Carbon $date): string
    {
        $db = self::getDatabase($date);

        if ($db === 'operational') {
            return "{$baseTable}_hot";
        } else {
            return "{$baseTable}_archive";
        }
    }

    /**
     * Create a query builder that automatically routes to correct database(s)
     * 
     * Handles three cases:
     * 1. All data in hot (recent queries)
     * 2. All data in archive (historical queries)
     * 3. Data spans both (uses UNION)
     * 
     * @param string $baseTable Base table name (without _hot or _archive suffix)
     * @param Carbon $startDate Start date of query range
     * @param Carbon $endDate End date of query range
     * @return Builder Query builder ready for additional filtering
     */
    public static function query(string $baseTable, Carbon $startDate, Carbon $endDate): Builder
    {
        $cutoff = Carbon::now()->subDays(self::HOT_DATA_DAYS);

        // Case 1: All data is in archive (end date is older than cutoff)
        if ($endDate < $cutoff) {
            Log::debug("Routing to archive only", [
                'table' => $baseTable,
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString()
            ]);

            return DB::connection('analytics')
                ->table("{$baseTable}_archive")
                ->whereBetween('business_date', [
                    $startDate->toDateString(), 
                    $endDate->toDateString()
                ]);
        }

        // Case 2: All data is in hot (start date is within hot period)
        if ($startDate >= $cutoff) {
            Log::debug("Routing to operational only", [
                'table' => $baseTable,
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString()
            ]);

            return DB::connection('operational')
                ->table("{$baseTable}_hot")
                ->whereBetween('business_date', [
                    $startDate->toDateString(), 
                    $endDate->toDateString()
                ]);
        }

        // Case 3: Query spans both databases - use UNION
        Log::debug("Routing to UNION (spans both databases)", [
            'table' => $baseTable,
            'start' => $startDate->toDateString(),
            'end' => $endDate->toDateString(),
            'cutoff' => $cutoff->toDateString()
        ]);

        $hotQuery = DB::connection('operational')
            ->table("{$baseTable}_hot")
            ->whereBetween('business_date', [
                $cutoff->toDateString(), 
                $endDate->toDateString()
            ]);

        $archiveQuery = DB::connection('analytics')
            ->table("{$baseTable}_archive")
            ->whereBetween('business_date', [
                $startDate->toDateString(), 
                $cutoff->copy()->subDay()->toDateString()
            ]);

        // Return UNION query
        return $archiveQuery->union($hotQuery);
    }

    /**
     * Check if a date range spans both databases
     * 
     * @param Carbon $startDate Start date
     * @param Carbon $endDate End date
     * @return bool True if query needs UNION
     */
    public static function spansDatabase(Carbon $startDate, Carbon $endDate): bool
    {
        $cutoff = Carbon::now()->subDays(self::HOT_DATA_DAYS);
        return $startDate < $cutoff && $endDate >= $cutoff;
    }

    /**
     * Get cutoff date between hot and archive data
     * 
     * @return Carbon Cutoff date
     */
    public static function getCutoffDate(): Carbon
    {
        return Carbon::now()->subDays(self::HOT_DATA_DAYS);
    }

    /**
     * Get statistics about data distribution across databases
     * 
     * Useful for monitoring and capacity planning
     * 
     * @param string $baseTable Base table name
     * @return array Statistics array
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
            'table' => $baseTable,
            'hot_rows' => $hotCount,
            'archive_rows' => $archiveCount,
            'total_rows' => $totalRows,
            'cutoff_date' => $cutoff->toDateString(),
            'hot_percentage' => $totalRows > 0 ? round(($hotCount / $totalRows) * 100, 2) : 0,
            'archive_percentage' => $totalRows > 0 ? round(($archiveCount / $totalRows) * 100, 2) : 0,
        ];
    }

    /**
     * Get data distribution for all tables
     * 
     * @return array Array of table statistics
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
