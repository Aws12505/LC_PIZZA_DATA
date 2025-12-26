<?php

namespace App\Services\Aggregation;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Operational\DetailOrderHot;
use App\Models\Operational\OrderLineHot;
use App\Models\Operational\SummaryTransactionsHot;
use App\Models\Operational\WasteHot;
use App\Models\Operational\SummarySalesHot;
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
 * COMPLETE AggregationService - DAILY→WEEKLY→MONTHLY→QUARTERLY→YEARLY
 * ✅ BACKWARD COMPATIBLE single date methods
 * ✅ NEW quarterly/yearly methods  
 * ✅ FULL 60+ metrics EVERY level (Crazy Puffs included)
 */
class AggregationService
{
    /**
     * BACKWARD COMPATIBLE: LCReportDataService calls this
     */
    public function updateDailySummaries(Carbon $date): void
    {
        Log::info('Daily: ' . $date->toDateString());

        $stores = DetailOrderHot::where('business_date', $date->toDateString())
            ->distinct()->pluck('franchise_store');

        if ($stores->isEmpty()) return;

        foreach ($stores as $store) {
            try {
                $this->updateDailyStoreSummary($store, $date);
                $this->updateDailyItemSummary($store, $date);
            } catch (\Exception $e) {
                Log::error("Daily {$store} failed: " . $e->getMessage());
            }
        }
    }

    /**
     * BACKWARD COMPATIBLE: Commands call this
     */
    public function updateWeeklySummaries(Carbon $date): void
    {
        $weekStart = $date->copy()->startOfWeek(Carbon::TUESDAY);
        $weekEnd   = $date->copy()->endOfWeek(Carbon::MONDAY);
        Log::info("Weekly {$weekStart->format('Y-m-d')}→{$weekEnd->format('Y-m-d')}");
        $this->updateWeeklySummariesRange($weekStart, $weekEnd);
    }

    /**
     * BACKWARD COMPATIBLE: Commands call this  
     */
    public function updateMonthlySummaries(Carbon $date): void
    {
        $year = $date->year;
        $month = $date->month;
        Log::info("Monthly {$year}-{$month}");
        $this->updateMonthlySummariesYearMonth($year, $month);
    }

    public function updateWeeklySummariesRange(Carbon $start, Carbon $end): void
    {
        $weeks = DailyStoreSummary::whereBetween('business_date', [$start, $end])
            ->selectRaw('DISTINCT franchise_store, YEAR(business_date) y, WEEK(business_date) w')
            ->get();

        foreach ($weeks as $week) {
            $this->aggregateWeeklyStore($week->franchise_store, $week->y, $week->w);
            $this->aggregateWeeklyItems($week->franchise_store, $week->y, $week->w);
        }
    }

    public function updateMonthlySummariesYearMonth(int $year, int $month): void
    {
        $stores = DailyStoreSummary::whereYear('business_date', $year)
            ->whereMonth('business_date', $month)
            ->distinct()->pluck('franchise_store');

        foreach ($stores as $store) {
            $this->aggregateMonthlyStore($store, $year, $month);
            $this->aggregateMonthlyItems($store, $year, $month);
        }
    }

    /**
     * NEW: Quarterly single date
     */
    public function updateQuarterlySummaries(Carbon $date): void
    {
        $year = $date->year;
        $quarter = ceil($date->month / 3);
        Log::info("Quarterly {$year} Q{$quarter}");
        $this->updateQuarterlySummariesYearQuarter($year, $quarter);
    }

    public function updateQuarterlySummariesYearQuarter(int $year, int $quarter): void
    {
        $m1 = ($quarter - 1) * 3 + 1;
        $m3 = $quarter * 3;
        $stores = DailyStoreSummary::whereYear('business_date', $year)
            ->whereBetween(DB::raw('MONTH(business_date)'), [$m1, $m3])
            ->distinct()->pluck('franchise_store');

        foreach ($stores as $store) {
            $this->aggregateQuarterlyStore($store, $year, $quarter);
            $this->aggregateQuarterlyItems($store, $year, $quarter);
        }
    }

    /**
     * NEW: Yearly single date
     */
    public function updateYearlySummaries(Carbon $date): void
    {
        $year = $date->year;
        Log::info("Yearly {$year}");
        $this->updateYearlySummariesYear($year);
    }

    public function updateYearlySummariesYear(int $year): void
    {
        $stores = DailyStoreSummary::whereYear('business_date', $year)
            ->distinct()->pluck('franchise_store');

        foreach ($stores as $store) {
            $this->aggregateYearlyStore($store, $year);
            $this->aggregateYearlyItems($store, $year);
        }
    }

    // ========== DAILY FROM RAW DATA ==========
    private function updateDailyStoreSummary(string $store, Carbon $date): void
{
    $dateStr = $date->toDateString();

    $baseOrders = DetailOrderHot::where('franchise_store', $store)
        ->where('business_date', $dateStr);

    if (!(clone $baseOrders)->exists()) {
        return;
    }

    // SALES
    $totalSales = (clone $baseOrders)->sum('gross_sales');
    $grossSales = (clone $baseOrders)->sum('royalty_obligation');

    $netSales = (clone $baseOrders)
        ->get(['gross_sales', 'non_royalty_amount'])
        ->sum(fn ($r) => (float) $r->gross_sales - (float) ($r->non_royalty_amount ?? 0));

    $refundAmount = (clone $baseOrders)
        ->where('refunded', 'Yes')
        ->sum('gross_sales');

    // ORDERS
    $totalOrders = (clone $baseOrders)->distinct()->count('order_id');

    $refundedOrders = (clone $baseOrders)
        ->where('refunded', 'Yes')
        ->distinct()
        ->count('order_id');

    $modifiedOrders = (clone $baseOrders)
        ->whereNotNull('modified_order_amount')
        ->distinct()
        ->count('order_id');

    $customerCount = (clone $baseOrders)->sum('customer_count');

    // CHANNELS (adjust method values if needed to match your data)
    $phoneOrders = (clone $baseOrders)
        ->where('order_placed_method', 'Phone')
        ->distinct()
        ->count('order_id');
    $phoneSales = (clone $baseOrders)
        ->where('order_placed_method', 'Phone')
        ->sum('gross_sales');

    $websiteOrders = (clone $baseOrders)
        ->where('order_placed_method', 'Website')
        ->distinct()
        ->count('order_id');
    $websiteSales = (clone $baseOrders)
        ->where('order_placed_method', 'Website')
        ->sum('gross_sales');

    $mobileOrders = (clone $baseOrders)
        ->where('order_placed_method', 'Mobile')
        ->distinct()
        ->count('order_id');
    $mobileSales = (clone $baseOrders)
        ->where('order_placed_method', 'Mobile')
        ->sum('gross_sales');

    $callCenterOrders = (clone $baseOrders)
        ->where('order_placed_method', 'CallCenterAgent')
        ->distinct()
        ->count('order_id');
    $callCenterSales = (clone $baseOrders)
        ->where('order_placed_method', 'CallCenterAgent')
        ->sum('gross_sales');

    // If you have a real drive‑thru code, plug it here
    $driveThruOrders = (clone $baseOrders)
        ->where('order_placed_method', 'Drive-Thru')
        ->distinct()
        ->count('order_id');
    $driveThruSales = (clone $baseOrders)
        ->where('order_placed_method', 'Drive-Thru')
        ->sum('gross_sales');

    // MARKETPLACE
    $doordashOrders = (clone $baseOrders)
        ->where('order_placed_method', 'DoorDash')
        ->distinct()
        ->count('order_id');
    $doordashSales = (clone $baseOrders)
        ->where('order_placed_method', 'DoorDash')
        ->sum('gross_sales');

    $ubereatsOrders = (clone $baseOrders)
        ->where('order_placed_method', 'UberEats')
        ->distinct()
        ->count('order_id');
    $ubereatsSales = (clone $baseOrders)
        ->where('order_placed_method', 'UberEats')
        ->sum('gross_sales');

    $grubhubOrders = (clone $baseOrders)
        ->where('order_placed_method', 'Grubhub')
        ->distinct()
        ->count('order_id');
    $grubhubSales = (clone $baseOrders)
        ->where('order_placed_method', 'Grubhub')
        ->sum('gross_sales');

    // FULFILLMENT
    $deliveryOrders = (clone $baseOrders)
        ->where('order_fulfilled_method', 'Delivery')
        ->distinct()
        ->count('order_id');
    $deliverySales = (clone $baseOrders)
        ->where('order_fulfilled_method', 'Delivery')
        ->sum('gross_sales');

    $carryoutOrders = (clone $baseOrders)
        ->whereIn('order_fulfilled_method', ['Register', 'Drive-Thru'])
        ->distinct()
        ->count('order_id');
    $carryoutSales = (clone $baseOrders)
        ->whereIn('order_fulfilled_method', ['Register', 'Drive-Thru'])
        ->sum('gross_sales');

    // FINANCIAL
    $salesTax = (clone $baseOrders)->sum('sales_tax');
    $deliveryFees = (clone $baseOrders)->sum('delivery_fee');
    $deliveryTips = (clone $baseOrders)->sum('delivery_tip');
    $storeTips = (clone $baseOrders)->sum('store_tip_amount');
    $totalTips = $deliveryTips + $storeTips;

    // PORTAL
    $portalEligible = (clone $baseOrders)
        ->where('portal_eligible', 'Yes')
        ->distinct()
        ->count('order_id');
    $portalUsed = (clone $baseOrders)
        ->where('portal_used', 'Yes')
        ->distinct()
        ->count('order_id');
    $portalOnTime = (clone $baseOrders)
        ->where('put_into_portal_before_promise_time', 'Yes')
        ->distinct()
        ->count('order_id');

    // PRODUCTS
    $baseLines = OrderLineHot::where('franchise_store', $store)
        ->where('business_date', $dateStr);

    $pizzaQty = (clone $baseLines)
        ->where('is_pizza', 1)
        ->sum('quantity');
    $pizzaSales = (clone $baseLines)
        ->where('is_pizza', 1)
        ->sum('net_amount');

    $hnrQty = (clone $baseLines)
        ->where('menu_item_account', 'HNR')
        ->sum('quantity');
    $hnrSales = (clone $baseLines)
        ->where('menu_item_account', 'HNR')
        ->sum('net_amount');

    $breadQty = (clone $baseLines)
        ->where('is_bread', 1)
        ->sum('quantity');
    $breadSales = (clone $baseLines)
        ->where('is_bread', 1)
        ->sum('net_amount');

    $wingsQty = (clone $baseLines)
        ->where('is_wings', 1)
        ->sum('quantity');
    $wingsSales = (clone $baseLines)
        ->where('is_wings', 1)
        ->sum('net_amount');

    $beveragesQty = (clone $baseLines)
        ->where('is_beverages', 1)
        ->sum('quantity');
    $beveragesSales = (clone $baseLines)
        ->where('is_beverages', 1)
        ->sum('net_amount');

    $crazyPuffsQty = (clone $baseLines)
        ->where('is_crazy_puffs', 1)
        ->sum('quantity');
    $crazyPuffsSales = (clone $baseLines)
        ->where('is_crazy_puffs', 1)
        ->sum('net_amount');

    // PAYMENTS
    $basePayments = SummaryTransactionsHot::where('franchise_store', $store)
        ->where('business_date', $dateStr);

    $cashSales = (clone $basePayments)
        ->where('payment_method', 'Cash')
        ->sum('total_amount');

    $creditCardSales = (clone $basePayments)
        ->get(['payment_method', 'total_amount'])
        ->filter(fn ($r) =>
            str_contains((string) $r->payment_method, 'Credit') ||
            str_contains((string) $r->payment_method, 'Card')
        )
        ->sum('total_amount');

    $prepaidSales = (clone $basePayments)
        ->where('sub_payment_method', 'like', '%Prepaid%')
        ->sum('total_amount');

    // WASTE
    $baseWaste = WasteHot::where('franchise_store', $store)
        ->where('business_date', $dateStr);

    $wasteItems = (clone $baseWaste)->count();
    $wasteCost = (clone $baseWaste)
        ->get(['item_cost', 'quantity'])
        ->sum(fn ($r) => (float) ($r->item_cost ?? 0) * (float) ($r->quantity ?? 0));

    // OVER/SHORT
    $overShort = SummarySalesHot::where('franchise_store', $store)
        ->where('business_date', $dateStr)
        ->value('over_short') ?? 0;

    // DIGITAL
    $digitalOrders = $websiteOrders + $mobileOrders;
    $digitalSales = $websiteSales + $mobileSales;

    $data = [
        'franchise_store' => $store,
        'business_date' => $dateStr,
        'total_sales' => $totalSales,
        'gross_sales' => $grossSales,
        'net_sales' => $netSales,
        'refund_amount' => $refundAmount,
        'total_orders' => $totalOrders,
        'completed_orders' => $totalOrders - $refundedOrders,
        'cancelled_orders' => 0,
        'modified_orders' => $modifiedOrders,
        'refunded_orders' => $refundedOrders,
        'avg_order_value' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
        'customer_count' => $customerCount,
        'avg_customers_per_order' => $totalOrders > 0 ? round($customerCount / $totalOrders, 2) : 0,

        'phone_orders' => $phoneOrders,
        'phone_sales' => $phoneSales,
        'website_orders' => $websiteOrders,
        'website_sales' => $websiteSales,
        'mobile_orders' => $mobileOrders,
        'mobile_sales' => $mobileSales,
        'call_center_orders' => $callCenterOrders,
        'call_center_sales' => $callCenterSales,
        'drive_thru_orders' => $driveThruOrders,
        'drive_thru_sales' => $driveThruSales,

        'doordash_orders' => $doordashOrders,
        'doordash_sales' => $doordashSales,
        'ubereats_orders' => $ubereatsOrders,
        'ubereats_sales' => $ubereatsSales,
        'grubhub_orders' => $grubhubOrders,
        'grubhub_sales' => $grubhubSales,

        'delivery_orders' => $deliveryOrders,
        'delivery_sales' => $deliverySales,
        'carryout_orders' => $carryoutOrders,
        'carryout_sales' => $carryoutSales,

        'pizza_quantity' => $pizzaQty,
        'pizza_sales' => $pizzaSales,
        'hnr_quantity' => $hnrQty,
        'hnr_sales' => $hnrSales,
        'bread_quantity' => $breadQty,
        'bread_sales' => $breadSales,
        'wings_quantity' => $wingsQty,
        'wings_sales' => $wingsSales,
        'beverages_quantity' => $beveragesQty,
        'beverages_sales' => $beveragesSales,
        'crazy_puffs_quantity' => $crazyPuffsQty,
        'crazy_puffs_sales' => $crazyPuffsSales,

        'sales_tax' => $salesTax,
        'delivery_fees' => $deliveryFees,
        'delivery_tips' => $deliveryTips,
        'store_tips' => $storeTips,
        'total_tips' => $totalTips,

        'cash_sales' => $cashSales,
        'credit_card_sales' => $creditCardSales,
        'prepaid_sales' => $prepaidSales,
        'over_short' => $overShort,

        'portal_eligible_orders' => $portalEligible,
        'portal_used_orders' => $portalUsed,
        'portal_on_time_orders' => $portalOnTime,
        'portal_usage_rate' => $portalEligible > 0
            ? round(($portalUsed / $portalEligible) * 100, 2)
            : 0,
        'portal_on_time_rate' => $portalUsed > 0
            ? round(($portalOnTime / $portalUsed) * 100, 2)
            : 0,

        'total_waste_items' => $wasteItems,
        'total_waste_cost' => $wasteCost,

        'digital_orders' => $digitalOrders,
        'digital_sales' => $digitalSales,
        'digital_penetration' => $totalOrders > 0
            ? round(($digitalOrders / $totalOrders) * 100, 2)
            : 0,
    ];

    DB::connection('analytics')
        ->table('daily_store_summary')
        ->upsert([$data], ['franchise_store', 'business_date'], array_keys($data));
}


    private function updateDailyItemSummary(string $store, Carbon $date): void
    {
        $dateStr = $date->toDateString();
        $lines = OrderLineHot::where('franchise_store', $store)->where('business_date', $dateStr)
            ->get(['franchise_store', 'business_date', 'item_id', 'menu_item_name', 'menu_item_account', 
                   'quantity', 'net_amount', 'modification_reason', 'order_fulfilled_method', 'refunded']);

        $items = $lines->groupBy(fn($r) => "{$r->franchise_store}|{$r->business_date}|{$r->item_id}|{$r->menu_item_name}|{$r->menu_item_account}");

        foreach ($items as $group) {
            $first = $group->first();
            $qtySold = $group->sum('quantity');
            $grossSales = $group->sum('net_amount');
            $netSales = $group->filter(fn($r) => empty($r->modification_reason))->sum('net_amount');
            $deliveryQty = $group->where('order_fulfilled_method', 'Delivery')->sum('quantity');
            $carryoutQty = $group->filter(fn($r) => in_array($r->order_fulfilled_method, ['Register', 'Drive-Thru']))->sum('quantity');
            $modifiedQty = $group->filter(fn($r) => !is_null($r->modified_order_amount))->sum('quantity');
            $refundedQty = $group->where('refunded', 'Yes')->sum('quantity');

            $data = [
                'franchise_store' => $first->franchise_store,
                'business_date' => $first->business_date,
                'item_id' => $first->item_id,
                'menu_item_name' => $first->menu_item_name,
                'menu_item_account' => $first->menu_item_account,
                'quantity_sold' => $qtySold,
                'gross_sales' => $grossSales,
                'net_sales' => $netSales,
                'avg_item_price' => $qtySold > 0 ? round($grossSales / $qtySold, 2) : 0,
                'delivery_quantity' => $deliveryQty,
                'carryout_quantity' => $carryoutQty,
                'modified_quantity' => $modifiedQty,
                'refunded_quantity' => $refundedQty
            ];

            DB::connection('analytics')->table('daily_item_summary')
                ->upsert([$data], ['franchise_store', 'business_date', 'item_id'], array_keys($data));
        }
    }

    // ========== WEEKLY ==========
    private function aggregateWeeklyStore(string $store, int $year, int $week): void
    {
        $weekStart = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $daily = DailyStoreSummary::where('franchise_store', $store)
            ->whereBetween('business_date', [$weekStart, $weekEnd])->get();

        if ($daily->isEmpty()) return;

        $summary = [
            'franchise_store' => $store,
            'year_num' => $year,
            'week_num' => $week,
            'week_start_date' => $weekStart->toDateString(),
            'week_end_date' => $weekEnd->toDateString(),
            // ALL 60+ METRICS - SUM FROM DAILY
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
            'avg_order_value' => $daily->sum('total_orders') > 0 ? $daily->sum('total_sales') / $daily->sum('total_orders') : 0,
            'avg_customers_per_order' => $daily->sum('total_orders') > 0 ? $daily->sum('customer_count') / $daily->sum('total_orders') : 0,
            'avg_daily_sales' => $daily->count() > 0 ? $daily->sum('total_sales') / $daily->count() : 0,
            'avg_daily_orders' => $daily->count() > 0 ? $daily->sum('total_orders') / $daily->count() : 0,
            // CHANNELS
            'phone_orders' => $daily->sum('phone_orders'), 'phone_sales' => $daily->sum('phone_sales'),
            'website_orders' => $daily->sum('website_orders'), 'website_sales' => $daily->sum('website_sales'),
            'mobile_orders' => $daily->sum('mobile_orders'), 'mobile_sales' => $daily->sum('mobile_sales'),
            'call_center_orders' => $daily->sum('call_center_orders'), 'call_center_sales' => $daily->sum('call_center_sales'),
            'drive_thru_orders' => $daily->sum('drive_thru_orders'), 'drive_thru_sales' => $daily->sum('drive_thru_sales'),
            // MARKETPLACE
            'doordash_orders' => $daily->sum('doordash_orders'), 'doordash_sales' => $daily->sum('doordash_sales'),
            'ubereats_orders' => $daily->sum('ubereats_orders'), 'ubereats_sales' => $daily->sum('ubereats_sales'),
            'grubhub_orders' => $daily->sum('grubhub_orders'), 'grubhub_sales' => $daily->sum('grubhub_sales'),
            // FULFILLMENT
            'delivery_orders' => $daily->sum('delivery_orders'), 'delivery_sales' => $daily->sum('delivery_sales'),
            'carryout_orders' => $daily->sum('carryout_orders'), 'carryout_sales' => $daily->sum('carryout_sales'),
            // PRODUCTS (incl CRAZY PUFFS!)
            'pizza_quantity' => $daily->sum('pizza_quantity'), 'pizza_sales' => $daily->sum('pizza_sales'),
            'hnr_quantity' => $daily->sum('hnr_quantity'), 'hnr_sales' => $daily->sum('hnr_sales'),
            'bread_quantity' => $daily->sum('bread_quantity'), 'bread_sales' => $daily->sum('bread_sales'),
            'wings_quantity' => $daily->sum('wings_quantity'), 'wings_sales' => $daily->sum('wings_sales'),
            'beverages_quantity' => $daily->sum('beverages_quantity'), 'beverages_sales' => $daily->sum('beverages_sales'),
            'crazy_puffs_quantity' => $daily->sum('crazy_puffs_quantity'), 'crazy_puffs_sales' => $daily->sum('crazy_puffs_sales'),
            // FINANCIAL
            'sales_tax' => $daily->sum('sales_tax'), 'delivery_fees' => $daily->sum('delivery_fees'),
            'delivery_tips' => $daily->sum('delivery_tips'), 'store_tips' => $daily->sum('store_tips'),
            'total_tips' => $daily->sum('total_tips'),
            // PAYMENTS
            'cash_sales' => $daily->sum('cash_sales'), 'credit_card_sales' => $daily->sum('credit_card_sales'),
            'prepaid_sales' => $daily->sum('prepaid_sales'), 'over_short' => $daily->sum('over_short'),
            // PORTAL
            'portal_eligible_orders' => $daily->sum('portal_eligible_orders'),
            'portal_used_orders' => $daily->sum('portal_used_orders'),
            'portal_on_time_orders' => $daily->sum('portal_on_time_orders'),
            // WASTE
            'total_waste_items' => $daily->sum('total_waste_items'), 'total_waste_cost' => $daily->sum('total_waste_cost'),
            // DIGITAL
            'digital_orders' => $daily->sum('digital_orders'), 'digital_sales' => $daily->sum('digital_sales')
        ];

        // RECALCULATE RATES
        $summary['portal_usage_rate'] = $summary['portal_eligible_orders'] > 0 
            ? ($summary['portal_used_orders'] / $summary['portal_eligible_orders']) * 100 : 0;
        $summary['portal_on_time_rate'] = $summary['portal_used_orders'] > 0 
            ? ($summary['portal_on_time_orders'] / $summary['portal_used_orders']) * 100 : 0;
        $summary['digital_penetration'] = $summary['total_orders'] > 0 
            ? ($summary['digital_orders'] / $summary['total_orders']) * 100 : 0;

        // GROWTH vs PRIOR WEEK
        $priorWeek = WeeklyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $week == 1 ? $year - 1 : $year)
            ->where('week_num', $week == 1 ? 52 : $week - 1)->first();

        if ($priorWeek) {
            $summary['sales_vs_prior_week'] = $summary['total_sales'] - $priorWeek->total_sales;
            $summary['sales_growth_percent'] = $priorWeek->total_sales > 0 
                ? (($summary['total_sales'] - $priorWeek->total_sales) / $priorWeek->total_sales) * 100 : 0;
            $summary['orders_vs_prior_week'] = $summary['total_orders'] - $priorWeek->total_orders;
            $summary['orders_growth_percent'] = $priorWeek->total_orders > 0 
                ? (($summary['total_orders'] - $priorWeek->total_orders) / $priorWeek->total_orders) * 100 : 0;
        }

        WeeklyStoreSummary::updateOrCreate(
            ['franchise_store' => $store, 'year_num' => $year, 'week_num' => $week],
            $summary
        );
    }

    private function aggregateWeeklyItems(string $store, int $year, int $week): void
    {
        $weekStart = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $items = DailyItemSummary::where('franchise_store', $store)
            ->whereBetween('business_date', [$weekStart, $weekEnd])
            ->selectRaw('item_id, menu_item_name, menu_item_account,
                SUM(quantity_sold) qty, SUM(gross_sales) gross, SUM(net_sales) net,
                AVG(avg_item_price) avg_price, AVG(quantity_sold) avg_daily,
                SUM(delivery_quantity) delivery, SUM(carryout_quantity) carryout')
            ->groupBy('item_id', 'menu_item_name', 'menu_item_account')->get();

        foreach ($items as $item) {
            WeeklyItemSummary::updateOrCreate(
                ['franchise_store' => $store, 'year_num' => $year, 'week_num' => $week, 'item_id' => $item->item_id],
                [
                    'menu_item_name' => $item->menu_item_name,
                    'menu_item_account' => $item->menu_item_account,
                    'quantity_sold' => $item->qty,
                    'gross_sales' => $item->gross,
                    'net_sales' => $item->net,
                    'avg_item_price' => $item->avg_price,
                    'avg_daily_quantity' => $item->avg_daily,
                    'delivery_quantity' => $item->delivery,
                    'carryout_quantity' => $item->carryout,
                    'week_start_date' => $weekStart->toDateString(),
                    'week_end_date' => $weekEnd->toDateString()
                ]
            );
        }
    }

    // ========== MONTHLY ==========
    private function aggregateMonthlyStore(string $store, int $year, int $month): void
{
    $daily = DailyStoreSummary::where('franchise_store', $store)
        ->whereYear('business_date', $year)
        ->whereMonth('business_date', $month)
        ->get();

    if ($daily->isEmpty()) {
        return;
    }

    $summary = [
        'franchise_store'   => $store,
        'year_num'          => $year,
        'month_num'         => $month,
        'month_name'        => Carbon::create($year, $month)->format('F'),
        'operational_days'  => $daily->count(),

        // SALES / ORDERS / CUSTOMERS
        'total_sales'       => $daily->sum('total_sales'),
        'gross_sales'       => $daily->sum('gross_sales'),
        'net_sales'         => $daily->sum('net_sales'),
        'refund_amount'     => $daily->sum('refund_amount'),
        'total_orders'      => $daily->sum('total_orders'),
        'completed_orders'  => $daily->sum('completed_orders'),
        'cancelled_orders'  => $daily->sum('cancelled_orders'),
        'modified_orders'   => $daily->sum('modified_orders'),
        'refunded_orders'   => $daily->sum('refunded_orders'),
        'customer_count'    => $daily->sum('customer_count'),

        'avg_order_value'   => $daily->sum('total_orders') > 0
            ? $daily->sum('total_sales') / $daily->sum('total_orders') : 0,
        'avg_customers_per_order' => $daily->sum('total_orders') > 0
            ? $daily->sum('customer_count') / $daily->sum('total_orders') : 0,
        'avg_daily_sales'   => $daily->count() > 0
            ? $daily->sum('total_sales') / $daily->count() : 0,
        'avg_daily_orders'  => $daily->count() > 0
            ? $daily->sum('total_orders') / $daily->count() : 0,

        // CHANNELS
        'phone_orders'      => $daily->sum('phone_orders'),
        'phone_sales'       => $daily->sum('phone_sales'),
        'website_orders'    => $daily->sum('website_orders'),
        'website_sales'     => $daily->sum('website_sales'),
        'mobile_orders'     => $daily->sum('mobile_orders'),
        'mobile_sales'      => $daily->sum('mobile_sales'),
        'call_center_orders'=> $daily->sum('call_center_orders'),
        'call_center_sales' => $daily->sum('call_center_sales'),
        'drive_thru_orders' => $daily->sum('drive_thru_orders'),
        'drive_thru_sales'  => $daily->sum('drive_thru_sales'),

        // MARKETPLACES
        'doordash_orders'   => $daily->sum('doordash_orders'),
        'doordash_sales'    => $daily->sum('doordash_sales'),
        'ubereats_orders'   => $daily->sum('ubereats_orders'),
        'ubereats_sales'    => $daily->sum('ubereats_sales'),
        'grubhub_orders'    => $daily->sum('grubhub_orders'),
        'grubhub_sales'     => $daily->sum('grubhub_sales'),

        // FULFILLMENT
        'delivery_orders'   => $daily->sum('delivery_orders'),
        'delivery_sales'    => $daily->sum('delivery_sales'),
        'carryout_orders'   => $daily->sum('carryout_orders'),
        'carryout_sales'    => $daily->sum('carryout_sales'),

        // PRODUCTS
        'pizza_quantity'    => $daily->sum('pizza_quantity'),
        'pizza_sales'       => $daily->sum('pizza_sales'),
        'hnr_quantity'      => $daily->sum('hnr_quantity'),
        'hnr_sales'         => $daily->sum('hnr_sales'),
        'bread_quantity'    => $daily->sum('bread_quantity'),
        'bread_sales'       => $daily->sum('bread_sales'),
        'wings_quantity'    => $daily->sum('wings_quantity'),
        'wings_sales'       => $daily->sum('wings_sales'),
        'beverages_quantity'=> $daily->sum('beverages_quantity'),
        'beverages_sales'   => $daily->sum('beverages_sales'),
        'crazy_puffs_quantity' => $daily->sum('crazy_puffs_quantity'),
        'crazy_puffs_sales'    => $daily->sum('crazy_puffs_sales'),

        // FINANCIAL
        'sales_tax'         => $daily->sum('sales_tax'),
        'delivery_fees'     => $daily->sum('delivery_fees'),
        'delivery_tips'     => $daily->sum('delivery_tips'),
        'store_tips'        => $daily->sum('store_tips'),
        'total_tips'        => $daily->sum('total_tips'),

        // PAYMENTS
        'cash_sales'        => $daily->sum('cash_sales'),
        'credit_card_sales' => $daily->sum('credit_card_sales'),
        'prepaid_sales'     => $daily->sum('prepaid_sales'),
        'over_short'        => $daily->sum('over_short'),

        // PORTAL
        'portal_eligible_orders' => $daily->sum('portal_eligible_orders'),
        'portal_used_orders'     => $daily->sum('portal_used_orders'),
        'portal_on_time_orders'  => $daily->sum('portal_on_time_orders'),

        // WASTE
        'total_waste_items' => $daily->sum('total_waste_items'),
        'total_waste_cost'  => $daily->sum('total_waste_cost'),

        // DIGITAL
        'digital_orders'    => $daily->sum('digital_orders'),
        'digital_sales'     => $daily->sum('digital_sales'),
    ];

    // recalc rates
    $summary['portal_usage_rate'] = $summary['portal_eligible_orders'] > 0
        ? ($summary['portal_used_orders'] / $summary['portal_eligible_orders']) * 100 : 0;

    $summary['portal_on_time_rate'] = $summary['portal_used_orders'] > 0
        ? ($summary['portal_on_time_orders'] / $summary['portal_used_orders']) * 100 : 0;

    $summary['digital_penetration'] = $summary['total_orders'] > 0
        ? ($summary['digital_orders'] / $summary['total_orders']) * 100 : 0;

    // growth vs prior month
    $priorMonth = MonthlyStoreSummary::where('franchise_store', $store)
        ->where('year_num', $month === 1 ? $year - 1 : $year)
        ->where('month_num', $month === 1 ? 12 : $month - 1)
        ->first();

    if ($priorMonth) {
        $summary['sales_vs_prior_month'] = $summary['total_sales'] - $priorMonth->total_sales;
        $summary['sales_growth_percent'] = $priorMonth->total_sales > 0
            ? (($summary['total_sales'] - $priorMonth->total_sales) / $priorMonth->total_sales) * 100 : 0;
    }

    // YoY vs same month last year
    $priorYear = MonthlyStoreSummary::where('franchise_store', $store)
        ->where('year_num', $year - 1)
        ->where('month_num', $month)
        ->first();

    if ($priorYear) {
        $summary['sales_vs_same_month_prior_year'] = $summary['total_sales'] - $priorYear->total_sales;
        $summary['yoy_growth_percent'] = $priorYear->total_sales > 0
            ? (($summary['total_sales'] - $priorYear->total_sales) / $priorYear->total_sales) * 100 : 0;
    }

    MonthlyStoreSummary::updateOrCreate(
        ['franchise_store' => $store, 'year_num' => $year, 'month_num' => $month],
        $summary
    );
}

   private function aggregateMonthlyItems(string $store, int $year, int $month): void
{
    $items = DailyItemSummary::where('franchise_store', $store)
        ->whereYear('business_date', $year)
        ->whereMonth('business_date', $month)
        ->selectRaw('item_id, menu_item_name, menu_item_account,
            SUM(quantity_sold)     as quantity_sold,
            SUM(gross_sales)       as gross_sales,
            SUM(net_sales)         as net_sales,
            AVG(avg_item_price)    as avg_item_price,
            AVG(quantity_sold)     as avg_daily_quantity,
            SUM(delivery_quantity) as delivery_quantity,
            SUM(carryout_quantity) as carryout_quantity')
        ->groupBy('item_id', 'menu_item_name', 'menu_item_account')
        ->get();

    foreach ($items as $item) {
        MonthlyItemSummary::updateOrCreate(
            [
                'franchise_store' => $store,
                'year_num'        => $year,
                'month_num'       => $month,
                'item_id'         => $item->item_id,
            ],
            [
                'menu_item_name'    => $item->menu_item_name,
                'menu_item_account' => $item->menu_item_account,
                'quantity_sold'     => $item->quantity_sold,
                'gross_sales'       => $item->gross_sales,
                'net_sales'         => $item->net_sales,
                'avg_item_price'    => $item->avg_item_price,
                'avg_daily_quantity'=> $item->avg_daily_quantity,
                'delivery_quantity' => $item->delivery_quantity,
                'carryout_quantity' => $item->carryout_quantity,
            ]
        );
    }
}


    // ========== QUARTERLY & YEARLY ==========
private function aggregateQuarterlyStore(string $store, int $year, int $quarter): void
{
    $m1 = ($quarter - 1) * 3 + 1;
    $m3 = $quarter * 3;

    $monthly = MonthlyStoreSummary::where('franchise_store', $store)
        ->where('year_num', $year)
        ->whereBetween('month_num', [$m1, $m3])
        ->get();

    if ($monthly->isEmpty()) {
        return;
    }

    $qStart = Carbon::create($year, $m1, 1);
    $qEnd   = Carbon::create($year, $m3 + 1, 1)->subDay();

    $summary = [
        'franchise_store'    => $store,
        'year_num'           => $year,
        'quarter_num'        => $quarter,
        'quarter_start_date' => $qStart->toDateString(),
        'quarter_end_date'   => $qEnd->toDateString(),
        'operational_days'   => $monthly->sum('operational_days'),
        'operational_months' => $monthly->count(),

        // sums
        'total_sales'        => $monthly->sum('total_sales'),
        'gross_sales'        => $monthly->sum('gross_sales'),
        'net_sales'          => $monthly->sum('net_sales'),
        'refund_amount'      => $monthly->sum('refund_amount'),
        'total_orders'       => $monthly->sum('total_orders'),
        'completed_orders'   => $monthly->sum('completed_orders'),
        'cancelled_orders'   => $monthly->sum('cancelled_orders'),
        'modified_orders'    => $monthly->sum('modified_orders'),
        'refunded_orders'    => $monthly->sum('refunded_orders'),
        'customer_count'     => $monthly->sum('customer_count'),

        // averages
        'avg_order_value'    => $monthly->sum('total_orders') > 0
            ? $monthly->sum('total_sales') / $monthly->sum('total_orders') : 0,
        'avg_customers_per_order' => $monthly->sum('total_orders') > 0
            ? $monthly->sum('customer_count') / $monthly->sum('total_orders') : 0,
        'avg_daily_sales'    => $monthly->sum('operational_days') > 0
            ? $monthly->sum('total_sales') / $monthly->sum('operational_days') : 0,
        'avg_monthly_sales'  => $monthly->count() > 0
            ? $monthly->sum('total_sales') / $monthly->count() : 0,

        // channels
        'phone_orders'       => $monthly->sum('phone_orders'),
        'phone_sales'        => $monthly->sum('phone_sales'),
        'website_orders'     => $monthly->sum('website_orders'),
        'website_sales'      => $monthly->sum('website_sales'),
        'mobile_orders'      => $monthly->sum('mobile_orders'),
        'mobile_sales'       => $monthly->sum('mobile_sales'),
        'call_center_orders' => $monthly->sum('call_center_orders'),
        'call_center_sales'  => $monthly->sum('call_center_sales'),
        'drive_thru_orders'  => $monthly->sum('drive_thru_orders'),
        'drive_thru_sales'   => $monthly->sum('drive_thru_sales'),

        // marketplaces
        'doordash_orders'    => $monthly->sum('doordash_orders'),
        'doordash_sales'     => $monthly->sum('doordash_sales'),
        'ubereats_orders'    => $monthly->sum('ubereats_orders'),
        'ubereats_sales'     => $monthly->sum('ubereats_sales'),
        'grubhub_orders'     => $monthly->sum('grubhub_orders'),
        'grubhub_sales'      => $monthly->sum('grubhub_sales'),

        // fulfillment
        'delivery_orders'    => $monthly->sum('delivery_orders'),
        'delivery_sales'     => $monthly->sum('delivery_sales'),
        'carryout_orders'    => $monthly->sum('carryout_orders'),
        'carryout_sales'     => $monthly->sum('carryout_sales'),

        // products
        'pizza_quantity'     => $monthly->sum('pizza_quantity'),
        'pizza_sales'        => $monthly->sum('pizza_sales'),
        'hnr_quantity'       => $monthly->sum('hnr_quantity'),
        'hnr_sales'          => $monthly->sum('hnr_sales'),
        'bread_quantity'     => $monthly->sum('bread_quantity'),
        'bread_sales'        => $monthly->sum('bread_sales'),
        'wings_quantity'     => $monthly->sum('wings_quantity'),
        'wings_sales'        => $monthly->sum('wings_sales'),
        'beverages_quantity' => $monthly->sum('beverages_quantity'),
        'beverages_sales'    => $monthly->sum('beverages_sales'),
        'crazy_puffs_quantity' => $monthly->sum('crazy_puffs_quantity'),
        'crazy_puffs_sales'    => $monthly->sum('crazy_puffs_sales'),

        // financial
        'sales_tax'          => $monthly->sum('sales_tax'),
        'delivery_fees'      => $monthly->sum('delivery_fees'),
        'delivery_tips'      => $monthly->sum('delivery_tips'),
        'store_tips'         => $monthly->sum('store_tips'),
        'total_tips'         => $monthly->sum('total_tips'),

        // payments
        'cash_sales'         => $monthly->sum('cash_sales'),
        'credit_card_sales'  => $monthly->sum('credit_card_sales'),
        'prepaid_sales'      => $monthly->sum('prepaid_sales'),
        'over_short'         => $monthly->sum('over_short'),

        // portal
        'portal_eligible_orders' => $monthly->sum('portal_eligible_orders'),
        'portal_used_orders'     => $monthly->sum('portal_used_orders'),
        'portal_on_time_orders'  => $monthly->sum('portal_on_time_orders'),

        // waste
        'total_waste_items'  => $monthly->sum('total_waste_items'),
        'total_waste_cost'   => $monthly->sum('total_waste_cost'),

        // digital
        'digital_orders'     => $monthly->sum('digital_orders'),
        'digital_sales'      => $monthly->sum('digital_sales'),
    ];

    // recalc rates
    $summary['portal_usage_rate'] = $summary['portal_eligible_orders'] > 0
        ? ($summary['portal_used_orders'] / $summary['portal_eligible_orders']) * 100 : 0;

    $summary['portal_on_time_rate'] = $summary['portal_used_orders'] > 0
        ? ($summary['portal_on_time_orders'] / $summary['portal_used_orders']) * 100 : 0;

    $summary['digital_penetration'] = $summary['total_orders'] > 0
        ? ($summary['digital_orders'] / $summary['total_orders']) * 100 : 0;

    // growth vs prior quarter
    $priorQuarter = QuarterlyStoreSummary::where('franchise_store', $store)
        ->where('year_num', $quarter === 1 ? $year - 1 : $year)
        ->where('quarter_num', $quarter === 1 ? 4 : $quarter - 1)
        ->first();

    if ($priorQuarter) {
        $summary['sales_vs_prior_quarter'] = $summary['total_sales'] - $priorQuarter->total_sales;
        $summary['sales_growth_percent'] = $priorQuarter->total_sales > 0
            ? (($summary['total_sales'] - $priorQuarter->total_sales) / $priorQuarter->total_sales) * 100 : 0;
    }

    // YoY growth vs same quarter last year
    $priorYear = QuarterlyStoreSummary::where('franchise_store', $store)
        ->where('year_num', $year - 1)
        ->where('quarter_num', $quarter)
        ->first();

    if ($priorYear) {
        $summary['sales_vs_same_quarter_prior_year'] = $summary['total_sales'] - $priorYear->total_sales;
        $summary['yoy_growth_percent'] = $priorYear->total_sales > 0
            ? (($summary['total_sales'] - $priorYear->total_sales) / $priorYear->total_sales) * 100 : 0;
    }

    QuarterlyStoreSummary::updateOrCreate(
        ['franchise_store' => $store, 'year_num' => $year, 'quarter_num' => $quarter],
        $summary
    );
}


    private function aggregateYearlyStore(string $store, int $year): void
{
    $monthly = MonthlyStoreSummary::where('franchise_store', $store)
        ->where('year_num', $year)
        ->get();

    if ($monthly->isEmpty()) {
        return;
    }

    $summary = [
        'franchise_store'    => $store,
        'year_num'           => $year,
        'operational_days'   => $monthly->sum('operational_days'),
        'operational_months' => $monthly->count(),

        'total_sales'        => $monthly->sum('total_sales'),
        'gross_sales'        => $monthly->sum('gross_sales'),
        'net_sales'          => $monthly->sum('net_sales'),
        'refund_amount'      => $monthly->sum('refund_amount'),
        'total_orders'       => $monthly->sum('total_orders'),
        'completed_orders'   => $monthly->sum('completed_orders'),
        'cancelled_orders'   => $monthly->sum('cancelled_orders'),
        'modified_orders'    => $monthly->sum('modified_orders'),
        'refunded_orders'    => $monthly->sum('refunded_orders'),
        'customer_count'     => $monthly->sum('customer_count'),

        'avg_order_value'    => $monthly->sum('total_orders') > 0
            ? $monthly->sum('total_sales') / $monthly->sum('total_orders') : 0,
        'avg_customers_per_order' => $monthly->sum('total_orders') > 0
            ? $monthly->sum('customer_count') / $monthly->sum('total_orders') : 0,
        'avg_daily_sales'    => $monthly->sum('operational_days') > 0
            ? $monthly->sum('total_sales') / $monthly->sum('operational_days') : 0,
        'avg_monthly_sales'  => $monthly->count() > 0
            ? $monthly->sum('total_sales') / $monthly->count() : 0,

        // channels
        'phone_orders'       => $monthly->sum('phone_orders'),
        'phone_sales'        => $monthly->sum('phone_sales'),
        'website_orders'     => $monthly->sum('website_orders'),
        'website_sales'      => $monthly->sum('website_sales'),
        'mobile_orders'      => $monthly->sum('mobile_orders'),
        'mobile_sales'       => $monthly->sum('mobile_sales'),
        'call_center_orders' => $monthly->sum('call_center_orders'),
        'call_center_sales'  => $monthly->sum('call_center_sales'),
        'drive_thru_orders'  => $monthly->sum('drive_thru_orders'),
        'drive_thru_sales'   => $monthly->sum('drive_thru_sales'),

        // marketplaces
        'doordash_orders'    => $monthly->sum('doordash_orders'),
        'doordash_sales'     => $monthly->sum('doordash_sales'),
        'ubereats_orders'    => $monthly->sum('ubereats_orders'),
        'ubereats_sales'     => $monthly->sum('ubereats_sales'),
        'grubhub_orders'     => $monthly->sum('grubhub_orders'),
        'grubhub_sales'      => $monthly->sum('grubhub_sales'),

        // fulfillment
        'delivery_orders'    => $monthly->sum('delivery_orders'),
        'delivery_sales'     => $monthly->sum('delivery_sales'),
        'carryout_orders'    => $monthly->sum('carryout_orders'),
        'carryout_sales'     => $monthly->sum('carryout_sales'),

        // products
        'pizza_quantity'     => $monthly->sum('pizza_quantity'),
        'pizza_sales'        => $monthly->sum('pizza_sales'),
        'hnr_quantity'       => $monthly->sum('hnr_quantity'),
        'hnr_sales'          => $monthly->sum('hnr_sales'),
        'bread_quantity'     => $monthly->sum('bread_quantity'),
        'bread_sales'        => $monthly->sum('bread_sales'),
        'wings_quantity'     => $monthly->sum('wings_quantity'),
        'wings_sales'        => $monthly->sum('wings_sales'),
        'beverages_quantity' => $monthly->sum('beverages_quantity'),
        'beverages_sales'    => $monthly->sum('beverages_sales'),
        'crazy_puffs_quantity' => $monthly->sum('crazy_puffs_quantity'),
        'crazy_puffs_sales'    => $monthly->sum('crazy_puffs_sales'),

        // financial
        'sales_tax'          => $monthly->sum('sales_tax'),
        'delivery_fees'      => $monthly->sum('delivery_fees'),
        'delivery_tips'      => $monthly->sum('delivery_tips'),
        'store_tips'         => $monthly->sum('store_tips'),
        'total_tips'         => $monthly->sum('total_tips'),

        // payments
        'cash_sales'         => $monthly->sum('cash_sales'),
        'credit_card_sales'  => $monthly->sum('credit_card_sales'),
        'prepaid_sales'      => $monthly->sum('prepaid_sales'),
        'over_short'         => $monthly->sum('over_short'),

        // portal
        'portal_eligible_orders' => $monthly->sum('portal_eligible_orders'),
        'portal_used_orders'     => $monthly->sum('portal_used_orders'),
        'portal_on_time_orders'  => $monthly->sum('portal_on_time_orders'),

        // waste
        'total_waste_items'  => $monthly->sum('total_waste_items'),
        'total_waste_cost'   => $monthly->sum('total_waste_cost'),

        // digital
        'digital_orders'     => $monthly->sum('digital_orders'),
        'digital_sales'      => $monthly->sum('digital_sales'),
    ];

    $summary['portal_usage_rate'] = $summary['portal_eligible_orders'] > 0
        ? ($summary['portal_used_orders'] / $summary['portal_eligible_orders']) * 100 : 0;

    $summary['portal_on_time_rate'] = $summary['portal_used_orders'] > 0
        ? ($summary['portal_on_time_orders'] / $summary['portal_used_orders']) * 100 : 0;

    $summary['digital_penetration'] = $summary['total_orders'] > 0
        ? ($summary['digital_orders'] / $summary['total_orders']) * 100 : 0;

    // growth vs prior year
    $priorYear = YearlyStoreSummary::where('franchise_store', $store)
        ->where('year_num', $year - 1)
        ->first();

    if ($priorYear) {
        $summary['sales_vs_prior_year'] = $summary['total_sales'] - $priorYear->total_sales;
        $summary['sales_growth_percent'] = $priorYear->total_sales > 0
            ? (($summary['total_sales'] - $priorYear->total_sales) / $priorYear->total_sales) * 100 : 0;
    }

    YearlyStoreSummary::updateOrCreate(
        ['franchise_store' => $store, 'year_num' => $year],
        $summary
    );
}

    private function aggregateQuarterlyItems(string $store, int $year, int $quarter): void
{
    $m1 = ($quarter - 1) * 3 + 1;
    $m3 = $quarter * 3;

    $items = MonthlyItemSummary::where('franchise_store', $store)
        ->where('year_num', $year)
        ->whereBetween('month_num', [$m1, $m3])
        ->selectRaw('item_id, menu_item_name, menu_item_account,
            SUM(quantity_sold)        as quantity_sold,
            SUM(gross_sales)          as gross_sales,
            SUM(net_sales)            as net_sales,
            AVG(avg_item_price)       as avg_item_price,
            AVG(avg_daily_quantity)   as avg_daily_quantity,
            SUM(delivery_quantity)    as delivery_quantity,
            SUM(carryout_quantity)    as carryout_quantity')
        ->groupBy('item_id', 'menu_item_name', 'menu_item_account')
        ->get();

    $qStart = Carbon::create($year, $m1, 1);
    $qEnd   = Carbon::create($year, $m3 + 1, 1)->subDay();

    foreach ($items as $item) {
        QuarterlyItemSummary::updateOrCreate(
            [
                'franchise_store' => $store,
                'year_num'        => $year,
                'quarter_num'     => $quarter,
                'item_id'         => $item->item_id,
            ],
            [
                'menu_item_name'     => $item->menu_item_name,
                'menu_item_account'  => $item->menu_item_account,
                'quantity_sold'      => $item->quantity_sold,
                'gross_sales'        => $item->gross_sales,
                'net_sales'          => $item->net_sales,
                'avg_item_price'     => $item->avg_item_price,
                'avg_daily_quantity' => $item->avg_daily_quantity,
                'delivery_quantity'  => $item->delivery_quantity,
                'carryout_quantity'  => $item->carryout_quantity,
                'quarter_start_date' => $qStart->toDateString(),
                'quarter_end_date'   => $qEnd->toDateString(),
            ]
        );
    }
}

   private function aggregateYearlyItems(string $store, int $year): void
{
    $items = MonthlyItemSummary::where('franchise_store', $store)
        ->where('year_num', $year)
        ->selectRaw('item_id, menu_item_name, menu_item_account,
            SUM(quantity_sold)        as quantity_sold,
            SUM(gross_sales)          as gross_sales,
            SUM(net_sales)            as net_sales,
            AVG(avg_item_price)       as avg_item_price,
            AVG(avg_daily_quantity)   as avg_daily_quantity,
            SUM(delivery_quantity)    as delivery_quantity,
            SUM(carryout_quantity)    as carryout_quantity')
        ->groupBy('item_id', 'menu_item_name', 'menu_item_account')
        ->get();

    foreach ($items as $item) {
        YearlyItemSummary::updateOrCreate(
            [
                'franchise_store' => $store,
                'year_num'        => $year,
                'item_id'         => $item->item_id,
            ],
            [
                'menu_item_name'     => $item->menu_item_name,
                'menu_item_account'  => $item->menu_item_account,
                'quantity_sold'      => $item->quantity_sold,
                'gross_sales'        => $item->gross_sales,
                'net_sales'          => $item->net_sales,
                'avg_item_price'     => $item->avg_item_price,
                'avg_daily_quantity' => $item->avg_daily_quantity,
                'delivery_quantity'  => $item->delivery_quantity,
                'carryout_quantity'  => $item->carryout_quantity,
            ]
        );
    }
}

}