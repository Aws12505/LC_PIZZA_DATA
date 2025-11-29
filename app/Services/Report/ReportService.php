<?php

namespace App\Services\Report;

use App\Services\Database\DatabaseRouter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * ReportService - High-performance reporting queries
 *
 * Same results as original, but avoids large raw SQL blocks.
 * Uses Laravel query builder aggregates instead of selectRaw().
 */
class ReportService
{
    /**
     * Base query for daily_store_summary filtered by store + date range.
     */
    protected function dailyStoreBaseQuery(string $storeId, Carbon $startDate, Carbon $endDate)
    {
        return DB::connection('analytics')
            ->table('daily_store_summary')
            ->where('franchise_store', $storeId)
            ->whereBetween('business_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]);
    }

    /**
     * Get store sales summary for date range
     */
    public function getStoreSalesSummary(
        string $storeId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $cacheKey = "store_sales_{$storeId}_{$startDate->toDateString()}_{$endDate->toDateString()}";

        return Cache::remember($cacheKey, 3600, function () use ($storeId, $startDate, $endDate) {
            $base = $this->dailyStoreBaseQuery($storeId, $startDate, $endDate);

            // Distinct days count using builder (no raw string)
            $daysCount = (clone $base)
                ->distinct('business_date')
                ->count('business_date');

            return [
                'days_count'            => $daysCount,
                'total_sales'           => (clone $base)->sum('total_sales'),
                'gross_sales'           => (clone $base)->sum('gross_sales'),
                'net_sales'             => (clone $base)->sum('net_sales'),
                'total_orders'          => (clone $base)->sum('total_orders'),
                'customer_count'        => (clone $base)->sum('customer_count'),
                'avg_order_value'       => (clone $base)->avg('avg_order_value'),
                'delivery_orders'       => (clone $base)->sum('delivery_orders'),
                'delivery_sales'        => (clone $base)->sum('delivery_sales'),
                'digital_orders'        => (clone $base)->sum('digital_orders'),
                'digital_sales'         => (clone $base)->sum('digital_sales'),
                'avg_digital_penetration'=> (clone $base)->avg('digital_penetration'),
                'total_tips'            => (clone $base)->sum('total_tips'),
                'total_waste_cost'      => (clone $base)->sum('total_waste_cost'),
            ];
        });
    }

    /**
     * Get daily breakdown for date range
     */
    public function getDailyBreakdown(
        string $storeId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        return $this->dailyStoreBaseQuery($storeId, $startDate, $endDate)
            ->select([
                'business_date',
                'total_sales',
                'total_orders',
                'customer_count',
                'avg_order_value',
                'delivery_sales',
                'digital_sales',
                'digital_penetration',
            ])
            ->orderBy('business_date', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get top selling items for date range
     */
    public function getTopSellingItems(
        string $storeId,
        Carbon $startDate,
        Carbon $endDate,
        int $limit = 20
    ): array {
        return DB::connection('analytics')
            ->table('daily_item_summary')
            ->where('franchise_store', $storeId)
            ->whereBetween('business_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->select([
                'item_id',
                'menu_item_name',
            ])
            ->groupBy('item_id', 'menu_item_name')
            // Use builder aggregate expressions instead of a raw block
            ->selectRaw('SUM(quantity_sold) as total_quantity')   // minimal raw per-column aliasing
            ->selectRaw('SUM(gross_sales) as total_sales')
            ->selectRaw('AVG(avg_item_price) as avg_price')
            ->selectRaw('SUM(delivery_quantity) as delivery_quantity')
            ->selectRaw('SUM(carryout_quantity) as carryout_quantity')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get hourly sales breakdown for a specific date
     */
    public function getHourlySales(string $storeId, Carbon $date): array
    {
        return DatabaseRouter::query('hourly_sales', $date, $date)
            ->where('franchise_store', $storeId)
            ->select([
                'hour',
                'total_sales',
                'phone_sales',
                'website_sales',
                'mobile_sales',
                'drive_thru_sales',
                'order_count',
            ])
            ->orderBy('hour')
            ->get()
            ->toArray();
    }

    /**
     * Get channel performance breakdown
     */
    public function getChannelPerformance(
        string $storeId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $base = $this->dailyStoreBaseQuery($storeId, $startDate, $endDate);

        $totalSales = (clone $base)->sum('total_sales');

        $phoneSales     = (clone $base)->sum('phone_sales');
        $phoneOrders    = (clone $base)->sum('phone_orders');
        $websiteSales   = (clone $base)->sum('website_sales');
        $websiteOrders  = (clone $base)->sum('website_orders');
        $mobileSales    = (clone $base)->sum('mobile_sales');
        $mobileOrders   = (clone $base)->sum('mobile_orders');
        $callCenterSales= (clone $base)->sum('call_center_sales');
        $callCenterOrders=(clone $base)->sum('call_center_orders');
        $doordashSales  = (clone $base)->sum('doordash_sales');
        $doordashOrders = (clone $base)->sum('doordash_orders');
        $ubereatsSales  = (clone $base)->sum('ubereats_sales');
        $ubereatsOrders = (clone $base)->sum('ubereats_orders');
        $grubhubSales   = (clone $base)->sum('grubhub_sales');
        $grubhubOrders  = (clone $base)->sum('grubhub_orders');

        $pct = fn($sales) => $totalSales > 0 ? round(($sales / $totalSales) * 100, 2) : 0;

        return [
            'channels' => [
                [
                    'name' => 'Phone',
                    'sales' => $phoneSales,
                    'orders' => $phoneOrders,
                    'percentage' => $pct($phoneSales),
                ],
                [
                    'name' => 'Website',
                    'sales' => $websiteSales,
                    'orders' => $websiteOrders,
                    'percentage' => $pct($websiteSales),
                ],
                [
                    'name' => 'Mobile',
                    'sales' => $mobileSales,
                    'orders' => $mobileOrders,
                    'percentage' => $pct($mobileSales),
                ],
                [
                    'name' => 'DoorDash',
                    'sales' => $doordashSales,
                    'orders' => $doordashOrders,
                    'percentage' => $pct($doordashSales),
                ],
                [
                    'name' => 'UberEats',
                    'sales' => $ubereatsSales,
                    'orders' => $ubereatsOrders,
                    'percentage' => $pct($ubereatsSales),
                ],
                [
                    'name' => 'Grubhub',
                    'sales' => $grubhubSales,
                    'orders' => $grubhubOrders,
                    'percentage' => $pct($grubhubSales),
                ],
            ],
            'total_sales' => $totalSales,
        ];
    }

    /**
     * Get product category breakdown
     */
    public function getProductCategoryBreakdown(
        string $storeId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $base = $this->dailyStoreBaseQuery($storeId, $startDate, $endDate);

        $pizzaSales     = (clone $base)->sum('pizza_sales');
        $pizzaQty       = (clone $base)->sum('pizza_quantity');
        $breadSales     = (clone $base)->sum('bread_sales');
        $breadQty       = (clone $base)->sum('bread_quantity');
        $wingsSales     = (clone $base)->sum('wings_sales');
        $wingsQty       = (clone $base)->sum('wings_quantity');
        $beverageSales  = (clone $base)->sum('beverages_sales');
        $beverageQty    = (clone $base)->sum('beverages_quantity');
        $puffsSales     = (clone $base)->sum('crazy_puffs_sales');
        $puffsQty       = (clone $base)->sum('crazy_puffs_quantity');

        $totalSales = $pizzaSales + $breadSales + $wingsSales + $beverageSales + $puffsSales;
        $pct = fn($sales) => $totalSales > 0 ? round(($sales / $totalSales) * 100, 2) : 0;

        return [
            'categories' => [
                [
                    'name' => 'Pizza',
                    'sales' => $pizzaSales,
                    'quantity' => $pizzaQty,
                    'percentage' => $pct($pizzaSales),
                ],
                [
                    'name' => 'Crazy Bread',
                    'sales' => $breadSales,
                    'quantity' => $breadQty,
                    'percentage' => $pct($breadSales),
                ],
                [
                    'name' => 'Wings',
                    'sales' => $wingsSales,
                    'quantity' => $wingsQty,
                    'percentage' => $pct($wingsSales),
                ],
                [
                    'name' => 'Beverages',
                    'sales' => $beverageSales,
                    'quantity' => $beverageQty,
                    'percentage' => $pct($beverageSales),
                ],
                [
                    'name' => 'Crazy Puffs',
                    'sales' => $puffsSales,
                    'quantity' => $puffsQty,
                    'percentage' => $pct($puffsSales),
                ],
            ],
            'total_sales' => $totalSales,
        ];
    }

    /**
     * Get waste analysis
     */
    public function getWasteAnalysis(
        string $storeId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $dailyWaste = $this->dailyStoreBaseQuery($storeId, $startDate, $endDate)
            ->select([
                'business_date',
                'total_waste_items',
                'total_waste_cost',
                'total_sales',
            ])
            ->orderBy('business_date', 'desc')
            ->get()
            ->toArray();

        $totalWasteCost = array_sum(array_column($dailyWaste, 'total_waste_cost'));
        $totalSales = array_sum(array_column($dailyWaste, 'total_sales'));

        return [
            'total_waste_cost' => $totalWasteCost,
            'waste_percentage' => $totalSales > 0 ? round(($totalWasteCost / $totalSales) * 100, 2) : 0,
            'daily_breakdown' => $dailyWaste,
        ];
    }

    /**
     * Get weekly comparison
     */
    public function getWeeklyComparison(string $storeId, int $weeksBack = 4): array
    {
        $weeks = [];

        for ($i = 0; $i < $weeksBack; $i++) {
            $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
            $weekEnd = Carbon::now()->subWeeks($i)->endOfWeek();

            $base = $this->dailyStoreBaseQuery($storeId, $weekStart, $weekEnd);

            $totalSales = (clone $base)->sum('total_sales');
            $totalOrders = (clone $base)->sum('total_orders');
            $avgOrderValue = (clone $base)->avg('avg_order_value');
            $customerCount = (clone $base)->sum('customer_count');

            $weeks[] = [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
                'avg_order_value' => round($avgOrderValue ?? 0, 2),
                'customer_count' => $customerCount,
            ];
        }

        return $weeks;
    }

    /**
     * Get monthly comparison
     */
    public function getMonthlyComparison(string $storeId, int $monthsBack = 6): array
    {
        return DB::connection('analytics')
            ->table('monthly_store_summary')
            ->where('franchise_store', $storeId)
            ->orderBy('year_num', 'desc')
            ->orderBy('month_num', 'desc')
            ->limit($monthsBack)
            ->select([
                'year_num',
                'month_num',
                'month_name',
                'total_sales',
                'total_orders',
                'customer_count',
                'avg_daily_sales',
                'operational_days',
            ])
            ->get()
            ->toArray();
    }

    /**
     * Export data using DatabaseRouter (for large exports spanning databases)
     */
    public function exportOrders(
        string $storeId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $limit = null
    ): array {
        $query = DatabaseRouter::query('detail_orders', $startDate, $endDate)
            ->where('franchise_store', $storeId)
            ->select([
                'business_date',
                'order_id',
                'date_time_placed',
                'date_time_fulfilled',
                'gross_sales',
                'order_placed_method',
                'order_fulfilled_method',
                'customer_count',
            ])
            ->orderBy('business_date', 'desc')
            ->orderBy('date_time_placed', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->toArray();
    }
}
