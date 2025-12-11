<?php

namespace App\Services\Aggregation;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Aggregation\DailyStoreSummary;
use App\Models\Aggregation\DailyItemSummary;
use App\Models\Aggregation\WeeklyStoreSummary;
use App\Models\Aggregation\WeeklyItemSummary;
use App\Models\Aggregation\MonthlyStoreSummary;
use App\Models\Aggregation\MonthlyItemSummary;
use App\Models\Aggregation\QuarterlyStoreSummary;
use App\Models\Aggregation\QuarterlyItemSummary;
use App\Models\Aggregation\YearlyStoreSummary;
use App\Models\Aggregation\YearlyItemSummary;

/**
 * AggregationService - Build and maintain summary tables
 *
 * Creates pre-computed aggregations from daily summaries to enable
 * ultra-fast reporting without scanning billions of rows.
 *
 * All summaries (weekly, monthly, quarterly, yearly) have FULL metrics
 * matching daily_store_summary and daily_item_summary.
 */
class AggregationService
{
    /**
     * Update all weekly summaries for a specific date range
     *
     * Aggregates from daily_store_summary and daily_item_summary
     */
    public function updateWeeklySummaries(Carbon $startDate, Carbon $endDate): void
    {
        Log::info("Updating weekly summaries from {$startDate->toDateString()} to {$endDate->toDateString()}");

        // Get all unique stores and week combinations
        $dailyData = DailyStoreSummary::whereBetween('business_date', [$startDate, $endDate])
            ->selectRaw('DISTINCT franchise_store, YEAR(business_date) as year_num, WEEK(business_date) as week_num')
            ->get();

        foreach ($dailyData as $record) {
            $this->aggregateWeeklyStore($record->franchise_store, $record->year_num, $record->week_num);
            $this->aggregateWeeklyItems($record->franchise_store, $record->year_num, $record->week_num);
        }

        Log::info("Weekly summaries updated");
    }

    /**
     * Aggregate weekly store summary from daily data
     */
    private function aggregateWeeklyStore(string $store, int $year, int $week): void
    {
        // Calculate week start and end dates
        $weekStart = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        // SUM all numeric metrics from daily data for this week
        $daily = DailyStoreSummary::where('franchise_store', $store)
            ->whereBetween('business_date', [$weekStart, $weekEnd])
            ->get();

        if ($daily->isEmpty()) {
            return;
        }

        $summary = [
            'franchise_store' => $store,
            'year_num' => $year,
            'week_num' => $week,
            'week_start_date' => $weekStart->toDateString(),
            'week_end_date' => $weekEnd->toDateString(),

            // SALES - Sum all daily sales
            'total_sales' => $daily->sum('total_sales'),
            'gross_sales' => $daily->sum('gross_sales'),
            'net_sales' => $daily->sum('net_sales'),
            'refund_amount' => $daily->sum('refund_amount'),

            // ORDERS - Sum all daily orders
            'total_orders' => $daily->sum('total_orders'),
            'completed_orders' => $daily->sum('completed_orders'),
            'cancelled_orders' => $daily->sum('cancelled_orders'),
            'modified_orders' => $daily->sum('modified_orders'),
            'refunded_orders' => $daily->sum('refunded_orders'),

            // CUSTOMERS - Sum all daily customers
            'customer_count' => $daily->sum('customer_count'),

            // AVERAGES - Calculate from summed data
            'avg_order_value' => $daily->sum('total_orders') > 0
                ? $daily->sum('total_sales') / $daily->sum('total_orders')
                : 0,
            'avg_customers_per_order' => $daily->sum('total_orders') > 0
                ? $daily->sum('customer_count') / $daily->sum('total_orders')
                : 0,

            // CHANNELS - Sum all daily channel metrics
            'phone_orders' => $daily->sum('phone_orders'),
            'phone_sales' => $daily->sum('phone_sales'),
            'website_orders' => $daily->sum('website_orders'),
            'website_sales' => $daily->sum('website_sales'),
            'mobile_orders' => $daily->sum('mobile_orders'),
            'mobile_sales' => $daily->sum('mobile_sales'),
            'call_center_orders' => $daily->sum('call_center_orders'),
            'call_center_sales' => $daily->sum('call_center_sales'),
            'drive_thru_orders' => $daily->sum('drive_thru_orders'),
            'drive_thru_sales' => $daily->sum('drive_thru_sales'),

            // MARKETPLACES - Sum all daily marketplace metrics
            'doordash_orders' => $daily->sum('doordash_orders'),
            'doordash_sales' => $daily->sum('doordash_sales'),
            'ubereats_orders' => $daily->sum('ubereats_orders'),
            'ubereats_sales' => $daily->sum('ubereats_sales'),
            'grubhub_orders' => $daily->sum('grubhub_orders'),
            'grubhub_sales' => $daily->sum('grubhub_sales'),

            // FULFILLMENT - Sum all daily fulfillment metrics
            'delivery_orders' => $daily->sum('delivery_orders'),
            'delivery_sales' => $daily->sum('delivery_sales'),
            'carryout_orders' => $daily->sum('carryout_orders'),
            'carryout_sales' => $daily->sum('carryout_sales'),

            // PRODUCTS - Sum all daily product metrics
            'pizza_quantity' => $daily->sum('pizza_quantity'),
            'pizza_sales' => $daily->sum('pizza_sales'),
            'hnr_quantity' => $daily->sum('hnr_quantity'),
            'hnr_sales' => $daily->sum('hnr_sales'),
            'bread_quantity' => $daily->sum('bread_quantity'),
            'bread_sales' => $daily->sum('bread_sales'),
            'wings_quantity' => $daily->sum('wings_quantity'),
            'wings_sales' => $daily->sum('wings_sales'),
            'beverages_quantity' => $daily->sum('beverages_quantity'),
            'beverages_sales' => $daily->sum('beverages_sales'),
            'crazy_puffs_quantity' => $daily->sum('crazy_puffs_quantity'),
            'crazy_puffs_sales' => $daily->sum('crazy_puffs_sales'),

            // FINANCIAL - Sum all daily financial metrics
            'sales_tax' => $daily->sum('sales_tax'),
            'delivery_fees' => $daily->sum('delivery_fees'),
            'delivery_tips' => $daily->sum('delivery_tips'),
            'store_tips' => $daily->sum('store_tips'),
            'total_tips' => $daily->sum('total_tips'),

            // PAYMENTS - Sum all daily payment metrics
            'cash_sales' => $daily->sum('cash_sales'),
            'credit_card_sales' => $daily->sum('credit_card_sales'),
            'prepaid_sales' => $daily->sum('prepaid_sales'),
            'over_short' => $daily->sum('over_short'),

            // OPERATIONAL - Sum all daily operational metrics
            'portal_eligible_orders' => $daily->sum('portal_eligible_orders'),
            'portal_used_orders' => $daily->sum('portal_used_orders'),
            'portal_on_time_orders' => $daily->sum('portal_on_time_orders'),

            // WASTE - Sum all daily waste metrics
            'total_waste_items' => $daily->sum('total_waste_items'),
            'total_waste_cost' => $daily->sum('total_waste_cost'),

            // DIGITAL - Sum all daily digital metrics
            'digital_orders' => $daily->sum('digital_orders'),
            'digital_sales' => $daily->sum('digital_sales'),
        ];

        // Recalculate rates for this week
        $summary['portal_usage_rate'] = $summary['portal_eligible_orders'] > 0
            ? ($summary['portal_used_orders'] / $summary['portal_eligible_orders']) * 100
            : 0;
        $summary['portal_on_time_rate'] = $summary['portal_used_orders'] > 0
            ? ($summary['portal_on_time_orders'] / $summary['portal_used_orders']) * 100
            : 0;
        $summary['digital_penetration'] = $summary['total_orders'] > 0
            ? ($summary['digital_orders'] / $summary['total_orders']) * 100
            : 0;

        // Growth metrics vs prior week
        $priorWeek = WeeklyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $year - ($week == 1 ? 1 : 0))
            ->where('week_num', $week == 1 ? 52 : $week - 1)
            ->first();

        if ($priorWeek) {
            $summary['sales_vs_prior_week'] = $summary['total_sales'] - $priorWeek->total_sales;
            $summary['sales_growth_percent'] = $priorWeek->total_sales > 0
                ? (($summary['total_sales'] - $priorWeek->total_sales) / $priorWeek->total_sales) * 100
                : 0;
            $summary['orders_vs_prior_week'] = $summary['total_orders'] - $priorWeek->total_orders;
            $summary['orders_growth_percent'] = $priorWeek->total_orders > 0
                ? (($summary['total_orders'] - $priorWeek->total_orders) / $priorWeek->total_orders) * 100
                : 0;
        }

        // Upsert the record
        WeeklyStoreSummary::updateOrCreate(
            ['franchise_store' => $store, 'year_num' => $year, 'week_num' => $week],
            $summary
        );
    }

    /**
     * Aggregate weekly item summary from daily data
     */
    private function aggregateWeeklyItems(string $store, int $year, int $week): void
    {
        $weekStart = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $dailyItems = DailyItemSummary::where('franchise_store', $store)
            ->whereBetween('business_date', [$weekStart, $weekEnd])
            ->selectRaw('item_id, menu_item_name, menu_item_account, 
                        SUM(quantity_sold) as quantity_sold,
                        SUM(gross_sales) as gross_sales,
                        SUM(net_sales) as net_sales,
                        AVG(avg_item_price) as avg_item_price,
                        AVG(quantity_sold) as avg_daily_quantity,
                        SUM(delivery_quantity) as delivery_quantity,
                        SUM(carryout_quantity) as carryout_quantity')
            ->groupBy('item_id', 'menu_item_name', 'menu_item_account')
            ->get();

        foreach ($dailyItems as $item) {
            WeeklyItemSummary::updateOrCreate(
                ['franchise_store' => $store, 'year_num' => $year, 'week_num' => $week, 'item_id' => $item->item_id],
                [
                    'menu_item_name' => $item->menu_item_name,
                    'menu_item_account' => $item->menu_item_account,
                    'quantity_sold' => $item->quantity_sold,
                    'gross_sales' => $item->gross_sales,
                    'net_sales' => $item->net_sales,
                    'avg_item_price' => $item->avg_item_price,
                    'avg_daily_quantity' => $item->avg_daily_quantity,
                    'delivery_quantity' => $item->delivery_quantity,
                    'carryout_quantity' => $item->carryout_quantity,
                    'week_start_date' => $weekStart->toDateString(),
                    'week_end_date' => $weekEnd->toDateString(),
                ]
            );
        }
    }

    /**
     * Update all monthly summaries
     */
    public function updateMonthlySummaries(int $year, int $month): void
    {
        Log::info("Updating monthly summaries for {$year}-{$month}");

        $stores = DailyStoreSummary::whereYear('business_date', $year)
            ->whereMonth('business_date', $month)
            ->distinct()
            ->pluck('franchise_store');

        foreach ($stores as $store) {
            $this->aggregateMonthlyStore($store, $year, $month);
            $this->aggregateMonthlyItems($store, $year, $month);
        }

        Log::info("Monthly summaries updated");
    }

    /**
     * Aggregate monthly store summary from daily data
     */
    private function aggregateMonthlyStore(string $store, int $year, int $month): void
    {
        $daily = DailyStoreSummary::where('franchise_store', $store)
            ->whereYear('business_date', $year)
            ->whereMonth('business_date', $month)
            ->get();

        if ($daily->isEmpty()) {
            return;
        }

        $monthName = Carbon::create($year, $month)->format('F');

        $summary = [
            'franchise_store' => $store,
            'year_num' => $year,
            'month_num' => $month,
            'month_name' => $monthName,

            // All sales, order, channel, product, financial metrics (same as weekly)
            'total_sales' => $daily->sum('total_sales'),
            'gross_sales' => $daily->sum('gross_sales'),
            'net_sales' => $daily->sum('net_sales'),
            'refund_amount' => $daily->sum('refund_amount'),
            'total_orders' => $daily->sum('total_orders'),
            'completed_orders' => $daily->sum('completed_orders'),
            'cancelled_orders' => $daily->sum('cancelled_orders'),
            'modified_orders' => $daily->sum('modified_orders'),
            'refunded_orders' => $daily->sum('refunded_orders'),
            'customer_count' => $daily->sum('customer_count'),
            'operational_days' => $daily->count(),

            // Averages
            'avg_order_value' => $daily->sum('total_orders') > 0
                ? $daily->sum('total_sales') / $daily->sum('total_orders')
                : 0,
            'avg_customers_per_order' => $daily->sum('total_orders') > 0
                ? $daily->sum('customer_count') / $daily->sum('total_orders')
                : 0,
            'avg_daily_sales' => $daily->count() > 0
                ? $daily->sum('total_sales') / $daily->count()
                : 0,
            'avg_daily_orders' => $daily->count() > 0
                ? $daily->sum('total_orders') / $daily->count()
                : 0,

            // All channels
            'phone_orders' => $daily->sum('phone_orders'),
            'phone_sales' => $daily->sum('phone_sales'),
            'website_orders' => $daily->sum('website_orders'),
            'website_sales' => $daily->sum('website_sales'),
            'mobile_orders' => $daily->sum('mobile_orders'),
            'mobile_sales' => $daily->sum('mobile_sales'),
            'call_center_orders' => $daily->sum('call_center_orders'),
            'call_center_sales' => $daily->sum('call_center_sales'),
            'drive_thru_orders' => $daily->sum('drive_thru_orders'),
            'drive_thru_sales' => $daily->sum('drive_thru_sales'),

            // All marketplaces
            'doordash_orders' => $daily->sum('doordash_orders'),
            'doordash_sales' => $daily->sum('doordash_sales'),
            'ubereats_orders' => $daily->sum('ubereats_orders'),
            'ubereats_sales' => $daily->sum('ubereats_sales'),
            'grubhub_orders' => $daily->sum('grubhub_orders'),
            'grubhub_sales' => $daily->sum('grubhub_sales'),

            // All fulfillment
            'delivery_orders' => $daily->sum('delivery_orders'),
            'delivery_sales' => $daily->sum('delivery_sales'),
            'carryout_orders' => $daily->sum('carryout_orders'),
            'carryout_sales' => $daily->sum('carryout_sales'),

            // All products
            'pizza_quantity' => $daily->sum('pizza_quantity'),
            'pizza_sales' => $daily->sum('pizza_sales'),
            'hnr_quantity' => $daily->sum('hnr_quantity'),
            'hnr_sales' => $daily->sum('hnr_sales'),
            'bread_quantity' => $daily->sum('bread_quantity'),
            'bread_sales' => $daily->sum('bread_sales'),
            'wings_quantity' => $daily->sum('wings_quantity'),
            'wings_sales' => $daily->sum('wings_sales'),
            'beverages_quantity' => $daily->sum('beverages_quantity'),
            'beverages_sales' => $daily->sum('beverages_sales'),
            'crazy_puffs_quantity' => $daily->sum('crazy_puffs_quantity'),
            'crazy_puffs_sales' => $daily->sum('crazy_puffs_sales'),

            // All financial
            'sales_tax' => $daily->sum('sales_tax'),
            'delivery_fees' => $daily->sum('delivery_fees'),
            'delivery_tips' => $daily->sum('delivery_tips'),
            'store_tips' => $daily->sum('store_tips'),
            'total_tips' => $daily->sum('total_tips'),

            // All payments
            'cash_sales' => $daily->sum('cash_sales'),
            'credit_card_sales' => $daily->sum('credit_card_sales'),
            'prepaid_sales' => $daily->sum('prepaid_sales'),
            'over_short' => $daily->sum('over_short'),

            // All operational
            'portal_eligible_orders' => $daily->sum('portal_eligible_orders'),
            'portal_used_orders' => $daily->sum('portal_used_orders'),
            'portal_on_time_orders' => $daily->sum('portal_on_time_orders'),

            // All waste
            'total_waste_items' => $daily->sum('total_waste_items'),
            'total_waste_cost' => $daily->sum('total_waste_cost'),

            // All digital
            'digital_orders' => $daily->sum('digital_orders'),
            'digital_sales' => $daily->sum('digital_sales'),
        ];

        // Recalculate rates
        $summary['portal_usage_rate'] = $summary['portal_eligible_orders'] > 0
            ? ($summary['portal_used_orders'] / $summary['portal_eligible_orders']) * 100
            : 0;
        $summary['portal_on_time_rate'] = $summary['portal_used_orders'] > 0
            ? ($summary['portal_on_time_orders'] / $summary['portal_used_orders']) * 100
            : 0;
        $summary['digital_penetration'] = $summary['total_orders'] > 0
            ? ($summary['digital_orders'] / $summary['total_orders']) * 100
            : 0;

        // Growth vs prior month
        $priorMonth = MonthlyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $month == 1 ? $year - 1 : $year)
            ->where('month_num', $month == 1 ? 12 : $month - 1)
            ->first();

        if ($priorMonth) {
            $summary['sales_vs_prior_month'] = $summary['total_sales'] - $priorMonth->total_sales;
            $summary['sales_growth_percent'] = $priorMonth->total_sales > 0
                ? (($summary['total_sales'] - $priorMonth->total_sales) / $priorMonth->total_sales) * 100
                : 0;
        }

        // YoY growth
        $priorYear = MonthlyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $year - 1)
            ->where('month_num', $month)
            ->first();

        if ($priorYear) {
            $summary['sales_vs_same_month_prior_year'] = $summary['total_sales'] - $priorYear->total_sales;
            $summary['yoy_growth_percent'] = $priorYear->total_sales > 0
                ? (($summary['total_sales'] - $priorYear->total_sales) / $priorYear->total_sales) * 100
                : 0;
        }

        MonthlyStoreSummary::updateOrCreate(
            ['franchise_store' => $store, 'year_num' => $year, 'month_num' => $month],
            $summary
        );
    }

    /**
     * Aggregate monthly item summary from daily data
     */
    private function aggregateMonthlyItems(string $store, int $year, int $month): void
    {
        $dailyItems = DailyItemSummary::where('franchise_store', $store)
            ->whereYear('business_date', $year)
            ->whereMonth('business_date', $month)
            ->selectRaw('item_id, menu_item_name, menu_item_account,
                        SUM(quantity_sold) as quantity_sold,
                        SUM(gross_sales) as gross_sales,
                        SUM(net_sales) as net_sales,
                        AVG(avg_item_price) as avg_item_price,
                        AVG(quantity_sold) as avg_daily_quantity,
                        SUM(delivery_quantity) as delivery_quantity,
                        SUM(carryout_quantity) as carryout_quantity')
            ->groupBy('item_id', 'menu_item_name', 'menu_item_account')
            ->get();

        foreach ($dailyItems as $item) {
            MonthlyItemSummary::updateOrCreate(
                ['franchise_store' => $store, 'year_num' => $year, 'month_num' => $month, 'item_id' => $item->item_id],
                [
                    'menu_item_name' => $item->menu_item_name,
                    'menu_item_account' => $item->menu_item_account,
                    'quantity_sold' => $item->quantity_sold,
                    'gross_sales' => $item->gross_sales,
                    'net_sales' => $item->net_sales,
                    'avg_item_price' => $item->avg_item_price,
                    'avg_daily_quantity' => $item->avg_daily_quantity,
                    'delivery_quantity' => $item->delivery_quantity,
                    'carryout_quantity' => $item->carryout_quantity,
                ]
            );
        }
    }

    /**
     * Update all quarterly summaries
     */
    public function updateQuarterlySummaries(int $year, int $quarter): void
    {
        Log::info("Updating quarterly summaries for {$year} Q{$quarter}");

        $month1 = ($quarter - 1) * 3 + 1;
        $month3 = $quarter * 3;

        $stores = DailyStoreSummary::whereYear('business_date', $year)
            ->whereBetween(DB::raw('MONTH(business_date)'), [$month1, $month3])
            ->distinct()
            ->pluck('franchise_store');

        foreach ($stores as $store) {
            $this->aggregateQuarterlyStore($store, $year, $quarter);
            $this->aggregateQuarterlyItems($store, $year, $quarter);
        }

        Log::info("Quarterly summaries updated");
    }

    /**
     * Aggregate quarterly store summary from monthly data
     */
    private function aggregateQuarterlyStore(string $store, int $year, int $quarter): void
    {
        $month1 = ($quarter - 1) * 3 + 1;
        $month3 = $quarter * 3;

        $monthly = MonthlyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $year)
            ->whereBetween('month_num', [$month1, $month3])
            ->get();

        if ($monthly->isEmpty()) {
            return;
        }

        $quarterStart = Carbon::create($year, $month1, 1);
        $quarterEnd = Carbon::create($year, $month3 + 1, 1)->subDay();

        $summary = [
            'franchise_store' => $store,
            'year_num' => $year,
            'quarter_num' => $quarter,
            'quarter_start_date' => $quarterStart->toDateString(),
            'quarter_end_date' => $quarterEnd->toDateString(),

            // All metrics - sum from monthly
            'total_sales' => $monthly->sum('total_sales'),
            'gross_sales' => $monthly->sum('gross_sales'),
            'net_sales' => $monthly->sum('net_sales'),
            'refund_amount' => $monthly->sum('refund_amount'),
            'total_orders' => $monthly->sum('total_orders'),
            'completed_orders' => $monthly->sum('completed_orders'),
            'cancelled_orders' => $monthly->sum('cancelled_orders'),
            'modified_orders' => $monthly->sum('modified_orders'),
            'refunded_orders' => $monthly->sum('refunded_orders'),
            'customer_count' => $monthly->sum('customer_count'),
            'operational_days' => $monthly->sum('operational_days'),
            'operational_months' => $monthly->count(),

            'avg_order_value' => $monthly->sum('total_orders') > 0
                ? $monthly->sum('total_sales') / $monthly->sum('total_orders')
                : 0,
            'avg_customers_per_order' => $monthly->sum('total_orders') > 0
                ? $monthly->sum('customer_count') / $monthly->sum('total_orders')
                : 0,
            'avg_daily_sales' => $monthly->sum('operational_days') > 0
                ? $monthly->sum('total_sales') / $monthly->sum('operational_days')
                : 0,
            'avg_monthly_sales' => $monthly->count() > 0
                ? $monthly->sum('total_sales') / $monthly->count()
                : 0,

            // All channels
            'phone_orders' => $monthly->sum('phone_orders'),
            'phone_sales' => $monthly->sum('phone_sales'),
            'website_orders' => $monthly->sum('website_orders'),
            'website_sales' => $monthly->sum('website_sales'),
            'mobile_orders' => $monthly->sum('mobile_orders'),
            'mobile_sales' => $monthly->sum('mobile_sales'),
            'call_center_orders' => $monthly->sum('call_center_orders'),
            'call_center_sales' => $monthly->sum('call_center_sales'),
            'drive_thru_orders' => $monthly->sum('drive_thru_orders'),
            'drive_thru_sales' => $monthly->sum('drive_thru_sales'),

            // All marketplaces
            'doordash_orders' => $monthly->sum('doordash_orders'),
            'doordash_sales' => $monthly->sum('doordash_sales'),
            'ubereats_orders' => $monthly->sum('ubereats_orders'),
            'ubereats_sales' => $monthly->sum('ubereats_sales'),
            'grubhub_orders' => $monthly->sum('grubhub_orders'),
            'grubhub_sales' => $monthly->sum('grubhub_sales'),

            // All fulfillment
            'delivery_orders' => $monthly->sum('delivery_orders'),
            'delivery_sales' => $monthly->sum('delivery_sales'),
            'carryout_orders' => $monthly->sum('carryout_orders'),
            'carryout_sales' => $monthly->sum('carryout_sales'),

            // All products
            'pizza_quantity' => $monthly->sum('pizza_quantity'),
            'pizza_sales' => $monthly->sum('pizza_sales'),
            'hnr_quantity' => $monthly->sum('hnr_quantity'),
            'hnr_sales' => $monthly->sum('hnr_sales'),
            'bread_quantity' => $monthly->sum('bread_quantity'),
            'bread_sales' => $monthly->sum('bread_sales'),
            'wings_quantity' => $monthly->sum('wings_quantity'),
            'wings_sales' => $monthly->sum('wings_sales'),
            'beverages_quantity' => $monthly->sum('beverages_quantity'),
            'beverages_sales' => $monthly->sum('beverages_sales'),
            'crazy_puffs_quantity' => $monthly->sum('crazy_puffs_quantity'),
            'crazy_puffs_sales' => $monthly->sum('crazy_puffs_sales'),

            // All financial
            'sales_tax' => $monthly->sum('sales_tax'),
            'delivery_fees' => $monthly->sum('delivery_fees'),
            'delivery_tips' => $monthly->sum('delivery_tips'),
            'store_tips' => $monthly->sum('store_tips'),
            'total_tips' => $monthly->sum('total_tips'),

            // All payments
            'cash_sales' => $monthly->sum('cash_sales'),
            'credit_card_sales' => $monthly->sum('credit_card_sales'),
            'prepaid_sales' => $monthly->sum('prepaid_sales'),
            'over_short' => $monthly->sum('over_short'),

            // All operational
            'portal_eligible_orders' => $monthly->sum('portal_eligible_orders'),
            'portal_used_orders' => $monthly->sum('portal_used_orders'),
            'portal_on_time_orders' => $monthly->sum('portal_on_time_orders'),

            // All waste
            'total_waste_items' => $monthly->sum('total_waste_items'),
            'total_waste_cost' => $monthly->sum('total_waste_cost'),

            // All digital
            'digital_orders' => $monthly->sum('digital_orders'),
            'digital_sales' => $monthly->sum('digital_sales'),
        ];

        // Recalculate rates
        $summary['portal_usage_rate'] = $summary['portal_eligible_orders'] > 0
            ? ($summary['portal_used_orders'] / $summary['portal_eligible_orders']) * 100
            : 0;
        $summary['portal_on_time_rate'] = $summary['portal_used_orders'] > 0
            ? ($summary['portal_on_time_orders'] / $summary['portal_used_orders']) * 100
            : 0;
        $summary['digital_penetration'] = $summary['total_orders'] > 0
            ? ($summary['digital_orders'] / $summary['total_orders']) * 100
            : 0;

        // Growth vs prior quarter
        $priorQuarter = QuarterlyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $quarter == 1 ? $year - 1 : $year)
            ->where('quarter_num', $quarter == 1 ? 4 : $quarter - 1)
            ->first();

        if ($priorQuarter) {
            $summary['sales_vs_prior_quarter'] = $summary['total_sales'] - $priorQuarter->total_sales;
            $summary['sales_growth_percent'] = $priorQuarter->total_sales > 0
                ? (($summary['total_sales'] - $priorQuarter->total_sales) / $priorQuarter->total_sales) * 100
                : 0;
        }

        // YoY growth
        $priorYear = QuarterlyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $year - 1)
            ->where('quarter_num', $quarter)
            ->first();

        if ($priorYear) {
            $summary['sales_vs_same_quarter_prior_year'] = $summary['total_sales'] - $priorYear->total_sales;
            $summary['yoy_growth_percent'] = $priorYear->total_sales > 0
                ? (($summary['total_sales'] - $priorYear->total_sales) / $priorYear->total_sales) * 100
                : 0;
        }

        QuarterlyStoreSummary::updateOrCreate(
            ['franchise_store' => $store, 'year_num' => $year, 'quarter_num' => $quarter],
            $summary
        );
    }

    /**
     * Aggregate quarterly item summary from monthly data
     */
    private function aggregateQuarterlyItems(string $store, int $year, int $quarter): void
    {
        $month1 = ($quarter - 1) * 3 + 1;
        $month3 = $quarter * 3;

        $monthlyItems = MonthlyItemSummary::where('franchise_store', $store)
            ->where('year_num', $year)
            ->whereBetween('month_num', [$month1, $month3])
            ->selectRaw('item_id, menu_item_name, menu_item_account,
                        SUM(quantity_sold) as quantity_sold,
                        SUM(gross_sales) as gross_sales,
                        SUM(net_sales) as net_sales,
                        AVG(avg_item_price) as avg_item_price,
                        AVG(avg_daily_quantity) as avg_daily_quantity,
                        SUM(delivery_quantity) as delivery_quantity,
                        SUM(carryout_quantity) as carryout_quantity')
            ->groupBy('item_id', 'menu_item_name', 'menu_item_account')
            ->get();

        $quarterStart = Carbon::create($year, ($quarter - 1) * 3 + 1, 1);
        $quarterEnd = Carbon::create($year, $quarter * 3 + 1, 1)->subDay();

        foreach ($monthlyItems as $item) {
            QuarterlyItemSummary::updateOrCreate(
                ['franchise_store' => $store, 'year_num' => $year, 'quarter_num' => $quarter, 'item_id' => $item->item_id],
                [
                    'menu_item_name' => $item->menu_item_name,
                    'menu_item_account' => $item->menu_item_account,
                    'quantity_sold' => $item->quantity_sold,
                    'gross_sales' => $item->gross_sales,
                    'net_sales' => $item->net_sales,
                    'avg_item_price' => $item->avg_item_price,
                    'avg_daily_quantity' => $item->avg_daily_quantity,
                    'delivery_quantity' => $item->delivery_quantity,
                    'carryout_quantity' => $item->carryout_quantity,
                    'quarter_start_date' => $quarterStart->toDateString(),
                    'quarter_end_date' => $quarterEnd->toDateString(),
                ]
            );
        }
    }

    /**
     * Update all yearly summaries
     */
    public function updateYearlySummaries(int $year): void
    {
        Log::info("Updating yearly summaries for {$year}");

        $stores = DailyStoreSummary::whereYear('business_date', $year)
            ->distinct()
            ->pluck('franchise_store');

        foreach ($stores as $store) {
            $this->aggregateYearlyStore($store, $year);
            $this->aggregateYearlyItems($store, $year);
        }

        Log::info("Yearly summaries updated");
    }

    /**
     * Aggregate yearly store summary from monthly data
     */
    private function aggregateYearlyStore(string $store, int $year): void
    {
        $monthly = MonthlyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $year)
            ->get();

        if ($monthly->isEmpty()) {
            return;
        }

        $summary = [
            'franchise_store' => $store,
            'year_num' => $year,

            // All metrics - sum from monthly
            'total_sales' => $monthly->sum('total_sales'),
            'gross_sales' => $monthly->sum('gross_sales'),
            'net_sales' => $monthly->sum('net_sales'),
            'refund_amount' => $monthly->sum('refund_amount'),
            'total_orders' => $monthly->sum('total_orders'),
            'completed_orders' => $monthly->sum('completed_orders'),
            'cancelled_orders' => $monthly->sum('cancelled_orders'),
            'modified_orders' => $monthly->sum('modified_orders'),
            'refunded_orders' => $monthly->sum('refunded_orders'),
            'customer_count' => $monthly->sum('customer_count'),
            'operational_days' => $monthly->sum('operational_days'),
            'operational_months' => $monthly->count(),

            'avg_order_value' => $monthly->sum('total_orders') > 0
                ? $monthly->sum('total_sales') / $monthly->sum('total_orders')
                : 0,
            'avg_customers_per_order' => $monthly->sum('total_orders') > 0
                ? $monthly->sum('customer_count') / $monthly->sum('total_orders')
                : 0,
            'avg_daily_sales' => $monthly->sum('operational_days') > 0
                ? $monthly->sum('total_sales') / $monthly->sum('operational_days')
                : 0,
            'avg_monthly_sales' => $monthly->count() > 0
                ? $monthly->sum('total_sales') / $monthly->count()
                : 0,

            // All channels
            'phone_orders' => $monthly->sum('phone_orders'),
            'phone_sales' => $monthly->sum('phone_sales'),
            'website_orders' => $monthly->sum('website_orders'),
            'website_sales' => $monthly->sum('website_sales'),
            'mobile_orders' => $monthly->sum('mobile_orders'),
            'mobile_sales' => $monthly->sum('mobile_sales'),
            'call_center_orders' => $monthly->sum('call_center_orders'),
            'call_center_sales' => $monthly->sum('call_center_sales'),
            'drive_thru_orders' => $monthly->sum('drive_thru_orders'),
            'drive_thru_sales' => $monthly->sum('drive_thru_sales'),

            // All marketplaces
            'doordash_orders' => $monthly->sum('doordash_orders'),
            'doordash_sales' => $monthly->sum('doordash_sales'),
            'ubereats_orders' => $monthly->sum('ubereats_orders'),
            'ubereats_sales' => $monthly->sum('ubereats_sales'),
            'grubhub_orders' => $monthly->sum('grubhub_orders'),
            'grubhub_sales' => $monthly->sum('grubhub_sales'),

            // All fulfillment
            'delivery_orders' => $monthly->sum('delivery_orders'),
            'delivery_sales' => $monthly->sum('delivery_sales'),
            'carryout_orders' => $monthly->sum('carryout_orders'),
            'carryout_sales' => $monthly->sum('carryout_sales'),

            // All products
            'pizza_quantity' => $monthly->sum('pizza_quantity'),
            'pizza_sales' => $monthly->sum('pizza_sales'),
            'hnr_quantity' => $monthly->sum('hnr_quantity'),
            'hnr_sales' => $monthly->sum('hnr_sales'),
            'bread_quantity' => $monthly->sum('bread_quantity'),
            'bread_sales' => $monthly->sum('bread_sales'),
            'wings_quantity' => $monthly->sum('wings_quantity'),
            'wings_sales' => $monthly->sum('wings_sales'),
            'beverages_quantity' => $monthly->sum('beverages_quantity'),
            'beverages_sales' => $monthly->sum('beverages_sales'),
            'crazy_puffs_quantity' => $monthly->sum('crazy_puffs_quantity'),
            'crazy_puffs_sales' => $monthly->sum('crazy_puffs_sales'),

            // All financial
            'sales_tax' => $monthly->sum('sales_tax'),
            'delivery_fees' => $monthly->sum('delivery_fees'),
            'delivery_tips' => $monthly->sum('delivery_tips'),
            'store_tips' => $monthly->sum('store_tips'),
            'total_tips' => $monthly->sum('total_tips'),

            // All payments
            'cash_sales' => $monthly->sum('cash_sales'),
            'credit_card_sales' => $monthly->sum('credit_card_sales'),
            'prepaid_sales' => $monthly->sum('prepaid_sales'),
            'over_short' => $monthly->sum('over_short'),

            // All operational
            'portal_eligible_orders' => $monthly->sum('portal_eligible_orders'),
            'portal_used_orders' => $monthly->sum('portal_used_orders'),
            'portal_on_time_orders' => $monthly->sum('portal_on_time_orders'),

            // All waste
            'total_waste_items' => $monthly->sum('total_waste_items'),
            'total_waste_cost' => $monthly->sum('total_waste_cost'),

            // All digital
            'digital_orders' => $monthly->sum('digital_orders'),
            'digital_sales' => $monthly->sum('digital_sales'),
        ];

        // Recalculate rates
        $summary['portal_usage_rate'] = $summary['portal_eligible_orders'] > 0
            ? ($summary['portal_used_orders'] / $summary['portal_eligible_orders']) * 100
            : 0;
        $summary['portal_on_time_rate'] = $summary['portal_used_orders'] > 0
            ? ($summary['portal_on_time_orders'] / $summary['portal_used_orders']) * 100
            : 0;
        $summary['digital_penetration'] = $summary['total_orders'] > 0
            ? ($summary['digital_orders'] / $summary['total_orders']) * 100
            : 0;

        // Growth vs prior year
        $priorYear = YearlyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $year - 1)
            ->first();

        if ($priorYear) {
            $summary['sales_vs_prior_year'] = $summary['total_sales'] - $priorYear->total_sales;
            $summary['sales_growth_percent'] = $priorYear->total_sales > 0
                ? (($summary['total_sales'] - $priorYear->total_sales) / $priorYear->total_sales) * 100
                : 0;
        }

        YearlyStoreSummary::updateOrCreate(
            ['franchise_store' => $store, 'year_num' => $year],
            $summary
        );
    }

    /**
     * Aggregate yearly item summary from monthly data
     */
    private function aggregateYearlyItems(string $store, int $year): void
    {
        $monthlyItems = MonthlyItemSummary::where('franchise_store', $store)
            ->where('year_num', $year)
            ->selectRaw('item_id, menu_item_name, menu_item_account,
                        SUM(quantity_sold) as quantity_sold,
                        SUM(gross_sales) as gross_sales,
                        SUM(net_sales) as net_sales,
                        AVG(avg_item_price) as avg_item_price,
                        AVG(avg_daily_quantity) as avg_daily_quantity,
                        SUM(delivery_quantity) as delivery_quantity,
                        SUM(carryout_quantity) as carryout_quantity')
            ->groupBy('item_id', 'menu_item_name', 'menu_item_account')
            ->get();

        foreach ($monthlyItems as $item) {
            YearlyItemSummary::updateOrCreate(
                ['franchise_store' => $store, 'year_num' => $year, 'item_id' => $item->item_id],
                [
                    'menu_item_name' => $item->menu_item_name,
                    'menu_item_account' => $item->menu_item_account,
                    'quantity_sold' => $item->quantity_sold,
                    'gross_sales' => $item->gross_sales,
                    'net_sales' => $item->net_sales,
                    'avg_item_price' => $item->avg_item_price,
                    'avg_daily_quantity' => $item->avg_daily_quantity,
                    'delivery_quantity' => $item->delivery_quantity,
                    'carryout_quantity' => $item->carryout_quantity,
                ]
            );
        }
    }
}
