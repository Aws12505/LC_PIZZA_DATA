<?php

namespace App\Services;

use App\Models\Operational\DetailOrderHot;
use App\Models\Operational\OrderLineHot;
use App\Models\Operational\SummarySalesHot;
use App\Models\Operational\SummaryTransactionsHot;
use App\Models\Operational\WasteHot;
use App\Models\Aggregation\HourlyStoreSummary;
use App\Models\Aggregation\HourlyItemSummary;
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
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AggregationService
{
    /**
     * Update hourly summaries from RAW transactional data
     */
    public function updateHourlySummaries(Carbon $date): void
    {
        Log::info("Hourly aggregation for: {$date->toDateString()}");

        $stores = DetailOrderHot::where('business_date', $date->toDateString())
            ->distinct()
            ->pluck('franchise_store');

        if ($stores->isEmpty()) {
            Log::warning("No stores found for date: {$date->toDateString()}");
            return;
        }

        foreach ($stores as $store) {
            try {
                $this->updateHourlyStoreSummary($store, $date);
                $this->updateHourlyItemSummary($store, $date);
            } catch (\Exception $e) {
                Log::error("Hourly aggregation failed for {$store}: " . $e->getMessage());
            }
        }
    }

    /**
     * Update daily summaries from HOURLY data
     */
    public function updateDailySummaries(Carbon $date): void
    {
        Log::info("Daily aggregation for: {$date->toDateString()}");

        $stores = HourlyStoreSummary::where('business_date', $date->toDateString())
            ->distinct()
            ->pluck('franchise_store');

        if ($stores->isEmpty()) {
            Log::warning("No hourly data found for date: {$date->toDateString()}");
            return;
        }

        foreach ($stores as $store) {
            try {
                $this->aggregateDailyFromHourly($store, $date);
                $this->aggregateDailyItemsFromHourly($store, $date);
            } catch (\Exception $e) {
                Log::error("Daily aggregation failed for {$store}: " . $e->getMessage());
            }
        }
    }

    /**
     * Update weekly summaries from DAILY data
     */
    public function updateWeeklySummaries(Carbon $date): void
    {
        $weekStart = $date->copy()->startOfWeek(Carbon::TUESDAY);
        $weekEnd = $date->copy()->endOfWeek(Carbon::MONDAY);
        Log::info("Weekly aggregation: {$weekStart->toDateString()} to {$weekEnd->toDateString()}");

        $this->updateWeeklySummariesRange($weekStart, $weekEnd);
    }

    /**
     * Update monthly summaries from WEEKLY data
     */
    public function updateMonthlySummaries(Carbon $date): void
    {
        $year = $date->year;
        $month = $date->month;
        Log::info("Monthly aggregation: {$year}-{$month}");

        $this->updateMonthlySummariesYearMonth($year, $month);
    }

    /**
     * Update quarterly summaries from MONTHLY data
     */
    public function updateQuarterlySummaries(Carbon $date): void
    {
        $year = $date->year;
        $quarter = (int) ceil($date->month / 3);
        Log::info("Quarterly aggregation: {$year} Q{$quarter}");

        $this->updateQuarterlySummariesYearQuarter($year, $quarter);
    }

    /**
     * Update yearly summaries from QUARTERLY data
     */
    public function updateYearlySummaries(Carbon $date): void
    {
        $year = $date->year;
        Log::info("Yearly aggregation: {$year}");

        $this->updateYearlySummariesYear($year);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HOURLY AGGREGATION FROM RAW DATA
    // ═══════════════════════════════════════════════════════════════════════════

    private function updateHourlyStoreSummary(string $store, Carbon $date): void
    {
        $dateStr = $date->toDateString();

        // ✅ FIXED: Changed order_datetime to date_time_fulfilled
        $hours = DetailOrderHot::where('franchise_store', $store)
            ->where('business_date', $dateStr)
            ->selectRaw('DISTINCT HOUR(date_time_fulfilled) as hour')
            ->pluck('hour');

        foreach ($hours as $hour) {
            $this->aggregateHourlyStoreData($store, $dateStr, $hour);
        }
    }

    private function aggregateHourlyStoreData(string $store, string $date, int $hour): void
    {
        // ✅ FIXED: Changed order_datetime to date_time_fulfilled
        $baseOrders = DetailOrderHot::where('franchise_store', $store)
            ->where('business_date', $date)
            ->whereRaw('HOUR(date_time_fulfilled) = ?', [$hour]);

        if (!(clone $baseOrders)->exists()) {
            return;
        }

        // SALES
        $totalSales = (clone $baseOrders)->sum('royalty_obligation');
        $grossSales = (clone $baseOrders)->sum('gross_sales');
        $netSales = (clone $baseOrders)
            ->get(['gross_sales', 'non_royalty_amount'])
            ->sum(fn($r) => (float)$r->gross_sales - (float)($r->non_royalty_amount ?? 0));

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
            ->whereNotNull('override_approval_employee')
            ->where('override_approval_employee', '!=', '')
            ->distinct()
            ->count('order_id');

        // ✅ FIXED: Added cancelled orders detection
        $cancelledOrders = (clone $baseOrders)
            ->where('transaction_type', 'Cancelled')
            ->distinct()
            ->count('order_id');

        $customerCount = (clone $baseOrders)->sum('customer_count');

        // CHANNELS
        $phoneOrders = (clone $baseOrders)->where('order_placed_method', 'Phone')->distinct()->count('order_id');
        $phoneSales = (clone $baseOrders)->where('order_placed_method', 'Phone')->sum('royalty_obligation');

        $websiteOrders = (clone $baseOrders)->where('order_placed_method', 'Website')->distinct()->count('order_id');
        $websiteSales = (clone $baseOrders)->where('order_placed_method', 'Website')->sum('royalty_obligation');

        $mobileOrders = (clone $baseOrders)->where('order_placed_method', 'Mobile')->distinct()->count('order_id');
        $mobileSales = (clone $baseOrders)->where('order_placed_method', 'Mobile')->sum('royalty_obligation');

        $callCenterOrders = (clone $baseOrders)->where('order_placed_method', 'SoundHoundAgent')->distinct()->count('order_id');
        $callCenterSales = (clone $baseOrders)->where('order_placed_method', 'SoundHoundAgent')->sum('royalty_obligation');

        $driveThruOrders = (clone $baseOrders)->where('order_placed_method', 'Drive Thru')->distinct()->count('order_id');
        $driveThruSales = (clone $baseOrders)->where('order_placed_method', 'Drive Thru')->sum('royalty_obligation');

        // MARKETPLACE
        $doordashOrders = (clone $baseOrders)->where('order_placed_method', 'DoorDash')->distinct()->count('order_id');
        $doordashSales = (clone $baseOrders)->where('order_placed_method', 'DoorDash')->sum('royalty_obligation');

        $ubereatsOrders = (clone $baseOrders)->where('order_placed_method', 'UberEats')->distinct()->count('order_id');
        $ubereatsSales = (clone $baseOrders)->where('order_placed_method', 'UberEats')->sum('royalty_obligation');

        $grubhubOrders = (clone $baseOrders)->where('order_placed_method', 'Grubhub')->distinct()->count('order_id');
        $grubhubSales = (clone $baseOrders)->where('order_placed_method', 'Grubhub')->sum('royalty_obligation');

        // FULFILLMENT
        $deliveryOrders = (clone $baseOrders)->where('order_fulfilled_method', 'Delivery')->distinct()->count('order_id');
        $deliverySales = (clone $baseOrders)->where('order_fulfilled_method', 'Delivery')->sum('royalty_obligation');

        $carryoutOrders = (clone $baseOrders)
            ->whereIn('order_fulfilled_method', ['Register', 'Drive-Thru'])
            ->distinct()
            ->count('order_id');
        $carryoutSales = (clone $baseOrders)
            ->whereIn('order_fulfilled_method', ['Register', 'Drive-Thru'])
            ->sum('royalty_obligation');

        // FINANCIAL
        $salesTax = (clone $baseOrders)->sum('sales_tax');
        $deliveryFees = (clone $baseOrders)->sum('delivery_fee');
        $deliveryTips = (clone $baseOrders)->sum('delivery_tip');
        $storeTips = (clone $baseOrders)->sum('store_tip_amount');

        // PORTAL
        $portalEligible = (clone $baseOrders)->where('portal_eligible', 'Yes')->distinct()->count('order_id');
        $portalUsed = (clone $baseOrders)->where('portal_used', 'Yes')->distinct()->count('order_id');
        $portalOnTime = (clone $baseOrders)->where('put_into_portal_before_promise_time', 'Yes')->distinct()->count('order_id');

        // PRODUCTS
        // ✅ FIXED: Changed order_datetime to date_time_fulfilled
        $baseLines = OrderLineHot::where('franchise_store', $store)
            ->where('business_date', $date)
            ->whereRaw('HOUR(date_time_fulfilled) = ?', [$hour]);

        $pizzaQty = (clone $baseLines)->where('is_pizza', 1)->sum('quantity');
        $pizzaSales = (clone $baseLines)->where('is_pizza', 1)->sum('net_amount');

        $hnrQty = (clone $baseLines)->where('menu_item_account', 'HNR')->sum('quantity');
        $hnrSales = (clone $baseLines)->where('menu_item_account', 'HNR')->sum('net_amount');

        $breadQty = (clone $baseLines)->where('is_bread', 1)->sum('quantity');
        $breadSales = (clone $baseLines)->where('is_bread', 1)->sum('net_amount');

        $wingsQty = (clone $baseLines)->where('is_wings', 1)->sum('quantity');
        $wingsSales = (clone $baseLines)->where('is_wings', 1)->sum('net_amount');

        $beveragesQty = (clone $baseLines)->where('is_beverages', 1)->sum('quantity');
        $beveragesSales = (clone $baseLines)->where('is_beverages', 1)->sum('net_amount');

        $crazyPuffsQty = (clone $baseLines)->where('is_crazy_puffs', 1)->sum('quantity');
        $crazyPuffsSales = (clone $baseLines)->where('is_crazy_puffs', 1)->sum('net_amount');

        // ✅ FIXED: Payments extracted from order data (payment_methods field)
        // Since summary_transactions_hot is daily aggregate, we extract from orders
        $ordersWithPayments = (clone $baseOrders)->get(['payment_methods', 'royalty_obligation']);

        $cashSales = 0;
        $creditCardSales = 0;
        $prepaidSales = 0;

        foreach ($ordersWithPayments as $order) {
            $paymentMethod = (string)$order->payment_methods;
            $amount = (float)$order->royalty_obligation;

            if (stripos($paymentMethod, 'Cash') !== false) {
                $cashSales += $amount;
            } elseif (stripos($paymentMethod, 'Credit') !== false || stripos($paymentMethod, 'Card') !== false) {
                $creditCardSales += $amount;
            } elseif (stripos($paymentMethod, 'Prepaid') !== false) {
                $prepaidSales += $amount;
            }
        }

        // WASTE (hourly waste tracking)
        // ✅ FIXED: Changed waste_datetime to waste_date_time
        $baseWaste = WasteHot::where('franchise_store', $store)
            ->where('business_date', $date)
            ->whereRaw('HOUR(waste_date_time) = ?', [$hour]);

        $wasteItems = (clone $baseWaste)->count();
        $wasteCost = (clone $baseWaste)
            ->get(['item_cost', 'quantity'])
            ->sum(fn($r) => (float)($r->item_cost ?? 0) * (float)($r->quantity ?? 0));

        // OVER/SHORT (aggregate at daily level, set to 0 for hourly)
        $overShort = 0;

        // DIGITAL
        $digitalOrders = $websiteOrders + $mobileOrders;
        $digitalSales = $websiteSales + $mobileSales;

        $data = [
            'franchise_store' => $store,
            'business_date' => $date,
            'hour' => $hour,
            'total_sales' => round($totalSales, 2),
            'gross_sales' => round($grossSales, 2),
            'net_sales' => round($netSales, 2),
            'refund_amount' => round($refundAmount, 2),
            'total_orders' => $totalOrders,
            'completed_orders' => $totalOrders - $refundedOrders - $cancelledOrders,
            'cancelled_orders' => $cancelledOrders,
            'modified_orders' => $modifiedOrders,
            'refunded_orders' => $refundedOrders,
            'avg_order_value' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
            'customer_count' => $customerCount,
            'avg_customers_per_order' => $totalOrders > 0 ? round($customerCount / $totalOrders, 2) : 0,

            // Channels
            'phone_orders' => $phoneOrders,
            'phone_sales' => round($phoneSales, 2),
            'website_orders' => $websiteOrders,
            'website_sales' => round($websiteSales, 2),
            'mobile_orders' => $mobileOrders,
            'mobile_sales' => round($mobileSales, 2),
            'call_center_orders' => $callCenterOrders,
            'call_center_sales' => round($callCenterSales, 2),
            'drive_thru_orders' => $driveThruOrders,
            'drive_thru_sales' => round($driveThruSales, 2),

            // Marketplace
            'doordash_orders' => $doordashOrders,
            'doordash_sales' => round($doordashSales, 2),
            'ubereats_orders' => $ubereatsOrders,
            'ubereats_sales' => round($ubereatsSales, 2),
            'grubhub_orders' => $grubhubOrders,
            'grubhub_sales' => round($grubhubSales, 2),

            // Fulfillment
            'delivery_orders' => $deliveryOrders,
            'delivery_sales' => round($deliverySales, 2),
            'carryout_orders' => $carryoutOrders,
            'carryout_sales' => round($carryoutSales, 2),

            // Products
            'pizza_quantity' => $pizzaQty,
            'pizza_sales' => round($pizzaSales, 2),
            'hnr_quantity' => $hnrQty,
            'hnr_sales' => round($hnrSales, 2),
            'bread_quantity' => $breadQty,
            'bread_sales' => round($breadSales, 2),
            'wings_quantity' => $wingsQty,
            'wings_sales' => round($wingsSales, 2),
            'beverages_quantity' => $beveragesQty,
            'beverages_sales' => round($beveragesSales, 2),
            'crazy_puffs_quantity' => $crazyPuffsQty,
            'crazy_puffs_sales' => round($crazyPuffsSales, 2),

            // Financial
            'sales_tax' => round($salesTax, 2),
            'delivery_fees' => round($deliveryFees, 2),
            'delivery_tips' => round($deliveryTips, 2),
            'store_tips' => round($storeTips, 2),
            'total_tips' => round($deliveryTips + $storeTips, 2),

            // Payments
            'cash_sales' => round($cashSales, 2),
            'credit_card_sales' => round($creditCardSales, 2),
            'prepaid_sales' => round($prepaidSales, 2),
            'over_short' => round($overShort, 2),

            // Portal
            'portal_eligible_orders' => $portalEligible,
            'portal_used_orders' => $portalUsed,
            'portal_on_time_orders' => $portalOnTime,
            'portal_usage_rate' => $portalEligible > 0 ? round(($portalUsed / $portalEligible) * 100, 2) : 0,
            'portal_on_time_rate' => $portalUsed > 0 ? round(($portalOnTime / $portalUsed) * 100, 2) : 0,

            // Waste
            'total_waste_items' => $wasteItems,
            'total_waste_cost' => round($wasteCost, 2),

            // Digital
            'digital_orders' => $digitalOrders,
            'digital_sales' => round($digitalSales, 2),
            'digital_penetration' => $totalOrders > 0 ? round(($digitalOrders / $totalOrders) * 100, 2) : 0,
        ];

        HourlyStoreSummary::updateOrCreate(
            [
                'franchise_store' => $store,
                'business_date' => $date,
                'hour' => $hour
            ],
            $data
        );
    }

    private function updateHourlyItemSummary(string $store, Carbon $date): void
    {
        $dateStr = $date->toDateString();

        // ✅ FIXED: Changed order_datetime to date_time_fulfilled
        $hours = OrderLineHot::where('franchise_store', $store)
            ->where('business_date', $dateStr)
            ->selectRaw('DISTINCT HOUR(date_time_fulfilled) as hour')
            ->pluck('hour');

        foreach ($hours as $hour) {
            $this->aggregateHourlyItemData($store, $dateStr, $hour);
        }
    }

    private function aggregateHourlyItemData(string $store, string $date, int $hour): void
    {
        // ✅ FIXED: Changed order_datetime to date_time_fulfilled
        $lines = OrderLineHot::where('franchise_store', $store)
            ->where('business_date', $date)
            ->whereRaw('HOUR(date_time_fulfilled) = ?', [$hour])
            ->get([
                'franchise_store', 'business_date', 'item_id', 'menu_item_name',
                'menu_item_account', 'quantity', 'net_amount', 'modification_reason',
                'order_fulfilled_method', 'refunded', 'modified_order_amount'
            ]);

        $items = $lines->groupBy(fn($r) => 
            "{$r->franchise_store}|{$r->business_date}|{$r->item_id}|{$r->menu_item_name}|{$r->menu_item_account}"
        );

        foreach ($items as $group) {
            $first = $group->first();

            $data = [
                'franchise_store' => $first->franchise_store,
                'business_date' => $first->business_date,
                'hour' => $hour,
                'item_id' => $first->item_id,
                'menu_item_name' => $first->menu_item_name,
                'menu_item_account' => $first->menu_item_account,
                'quantity_sold' => $group->sum('quantity'),
                'gross_sales' => round($group->sum('net_amount'), 2),
                'net_sales' => round($group->filter(fn($r) => empty($r->modification_reason))->sum('net_amount'), 2),
                'avg_item_price' => $group->sum('quantity') > 0 ? 
                    round($group->sum('net_amount') / $group->sum('quantity'), 2) : 0,
                'delivery_quantity' => $group->where('order_fulfilled_method', 'Delivery')->sum('quantity'),
                'carryout_quantity' => $group->filter(fn($r) => 
                    in_array($r->order_fulfilled_method, ['Register', 'Drive-Thru'])
                )->sum('quantity'),
                'modified_quantity' => $group->filter(fn($r) => !empty($r->modified_order_amount))->sum('quantity'),
                'refunded_quantity' => $group->where('refunded', 'Yes')->sum('quantity')
            ];

            HourlyItemSummary::updateOrCreate(
                [
                    'franchise_store' => $first->franchise_store,
                    'business_date' => $first->business_date,
                    'hour' => $hour,
                    'item_id' => $first->item_id
                ],
                $data
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DAILY AGGREGATION FROM HOURLY
    // ═══════════════════════════════════════════════════════════════════════════

    private function aggregateDailyFromHourly(string $store, Carbon $date): void
    {
        $dateStr = $date->toDateString();
        $hourly = HourlyStoreSummary::where('franchise_store', $store)
            ->where('business_date', $dateStr)
            ->get();

        if ($hourly->isEmpty()) {
            return;
        }

        // Get over_short and payment totals from raw daily table
        $dailySummary = SummarySalesHot::where('franchise_store', $store)
            ->where('business_date', $dateStr)
            ->first();

        $overShort = $dailySummary->over_short ?? 0;

        // ✅ FIXED: Get actual payment data from daily summary table
        $cashSales = $dailySummary->cash_amount ?? 0;
        $creditCardSales = $dailySummary->credit_card_amount ?? 0;
        $prepaidSales = $dailySummary->prepaid_amount ?? 0;

        $summary = $this->sumStorePeriod($hourly, [
            'franchise_store' => $store,
            'business_date' => $dateStr,
            'over_short' => $overShort
        ]);

        // Override payment totals with accurate daily data
        $summary['cash_sales'] = round($cashSales, 2);
        $summary['credit_card_sales'] = round($creditCardSales, 2);
        $summary['prepaid_sales'] = round($prepaidSales, 2);

        DailyStoreSummary::updateOrCreate(
            [
                'franchise_store' => $store,
                'business_date' => $dateStr
            ],
            $summary
        );
    }

    private function aggregateDailyItemsFromHourly(string $store, Carbon $date): void
    {
        $dateStr = $date->toDateString();
        $items = HourlyItemSummary::where('franchise_store', $store)
            ->where('business_date', $dateStr)
            ->selectRaw('
                item_id,
                menu_item_name,
                menu_item_account,
                SUM(quantity_sold) as quantity_sold,
                SUM(gross_sales) as gross_sales,
                SUM(net_sales) as net_sales,
                AVG(avg_item_price) as avg_item_price,
                SUM(delivery_quantity) as delivery_quantity,
                SUM(carryout_quantity) as carryout_quantity,
                SUM(modified_quantity) as modified_quantity,
                SUM(refunded_quantity) as refunded_quantity
            ')
            ->groupBy('item_id', 'menu_item_name', 'menu_item_account')
            ->get();

        foreach ($items as $item) {
            DailyItemSummary::updateOrCreate(
                [
                    'franchise_store' => $store,
                    'business_date' => $dateStr,
                    'item_id' => $item->item_id
                ],
                [
                    'menu_item_name' => $item->menu_item_name,
                    'menu_item_account' => $item->menu_item_account,
                    'quantity_sold' => $item->quantity_sold,
                    'gross_sales' => round($item->gross_sales, 2),
                    'net_sales' => round($item->net_sales, 2),
                    'avg_item_price' => round($item->avg_item_price, 2),
                    'delivery_quantity' => $item->delivery_quantity,
                    'carryout_quantity' => $item->carryout_quantity,
                    'modified_quantity' => $item->modified_quantity,
                    'refunded_quantity' => $item->refunded_quantity
                ]
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // WEEKLY AGGREGATION FROM DAILY
    // ═══════════════════════════════════════════════════════════════════════════

    public function updateWeeklySummariesRange(Carbon $start, Carbon $end): void
    {
        $weeks = DailyStoreSummary::whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('DISTINCT franchise_store, YEAR(business_date) as y, WEEK(business_date, 3) as w')
            ->get();

        foreach ($weeks as $week) {
            $this->aggregateWeeklyStore($week->franchise_store, $week->y, $week->w);
            $this->aggregateWeeklyItems($week->franchise_store, $week->y, $week->w);
        }
    }

    private function aggregateWeeklyStore(string $store, int $year, int $week): void
    {
        $weekStart = Carbon::now()->setISODate($year, $week)->startOfWeek(Carbon::TUESDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::MONDAY);

        $daily = DailyStoreSummary::where('franchise_store', $store)
            ->whereBetween('business_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get();

        if ($daily->isEmpty()) {
            return;
        }

        $summary = $this->sumStorePeriod($daily, [
            'franchise_store' => $store,
            'year_num' => $year,
            'week_num' => $week,
            'week_start_date' => $weekStart->toDateString(),
            'week_end_date' => $weekEnd->toDateString()
        ]);

        // Calculate averages for weekly
        $daysCount = $daily->count();
        $summary['avg_daily_sales'] = $daysCount > 0 ? round($summary['total_sales'] / $daysCount, 2) : 0;
        $summary['avg_daily_orders'] = $daysCount > 0 ? round($summary['total_orders'] / $daysCount, 2) : 0;

        // Growth calculations
        $priorWeek = WeeklyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $week == 1 ? $year - 1 : $year)
            ->where('week_num', $week == 1 ? 52 : $week - 1)
            ->first();

        if ($priorWeek) {
            $summary['sales_vs_prior_week'] = round($summary['total_sales'] - $priorWeek->total_sales, 2);
            $summary['sales_growth_percent'] = $priorWeek->total_sales > 0
                ? round((($summary['total_sales'] - $priorWeek->total_sales) / $priorWeek->total_sales) * 100, 2)
                : 0;
            $summary['orders_vs_prior_week'] = $summary['total_orders'] - $priorWeek->total_orders;
            $summary['orders_growth_percent'] = $priorWeek->total_orders > 0
                ? round((($summary['total_orders'] - $priorWeek->total_orders) / $priorWeek->total_orders) * 100, 2)
                : 0;
        }

        WeeklyStoreSummary::updateOrCreate(
            [
                'franchise_store' => $store,
                'year_num' => $year,
                'week_num' => $week
            ],
            $summary
        );
    }

    private function aggregateWeeklyItems(string $store, int $year, int $week): void
    {
        $weekStart = Carbon::now()->setISODate($year, $week)->startOfWeek(Carbon::TUESDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::MONDAY);

        $items = DailyItemSummary::where('franchise_store', $store)
            ->whereBetween('business_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('
                item_id,
                menu_item_name,
                menu_item_account,
                SUM(quantity_sold) as quantity_sold,
                SUM(gross_sales) as gross_sales,
                SUM(net_sales) as net_sales,
                AVG(avg_item_price) as avg_item_price,
                AVG(quantity_sold) as avg_daily_quantity,
                SUM(delivery_quantity) as delivery_quantity,
                SUM(carryout_quantity) as carryout_quantity
            ')
            ->groupBy('item_id', 'menu_item_name', 'menu_item_account')
            ->get();

        foreach ($items as $item) {
            WeeklyItemSummary::updateOrCreate(
                [
                    'franchise_store' => $store,
                    'year_num' => $year,
                    'week_num' => $week,
                    'item_id' => $item->item_id
                ],
                [
                    'menu_item_name' => $item->menu_item_name,
                    'menu_item_account' => $item->menu_item_account,
                    'quantity_sold' => $item->quantity_sold,
                    'gross_sales' => round($item->gross_sales, 2),
                    'net_sales' => round($item->net_sales, 2),
                    'avg_item_price' => round($item->avg_item_price, 2),
                    'avg_daily_quantity' => round($item->avg_daily_quantity, 2),
                    'delivery_quantity' => $item->delivery_quantity,
                    'carryout_quantity' => $item->carryout_quantity,
                    'week_start_date' => $weekStart->toDateString(),
                    'week_end_date' => $weekEnd->toDateString()
                ]
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // MONTHLY AGGREGATION FROM WEEKLY
    // ═══════════════════════════════════════════════════════════════════════════

    public function updateMonthlySummariesYearMonth(int $year, int $month): void
    {
        $monthStart = Carbon::create($year, $month, 1);
        $monthEnd = $monthStart->copy()->endOfMonth();

        $stores = WeeklyStoreSummary::where('year_num', $year)
            ->where(function($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('week_start_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                  ->orWhereBetween('week_end_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            })
            ->distinct()
            ->pluck('franchise_store');

        foreach ($stores as $store) {
            $this->aggregateMonthlyStore($store, $year, $month);
            $this->aggregateMonthlyItems($store, $year, $month);
        }
    }

    private function aggregateMonthlyStore(string $store, int $year, int $month): void
    {
        $monthStart = Carbon::create($year, $month, 1);
        $monthEnd = $monthStart->copy()->endOfMonth();

        $weekly = WeeklyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $year)
            ->where(function($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('week_start_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                  ->orWhereBetween('week_end_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            })
            ->get();

        if ($weekly->isEmpty()) {
            return;
        }

        // Get operational days from daily table
        $operationalDays = DailyStoreSummary::where('franchise_store', $store)
            ->whereYear('business_date', $year)
            ->whereMonth('business_date', $month)
            ->count();

        $summary = $this->sumStorePeriod($weekly, [
            'franchise_store' => $store,
            'year_num' => $year,
            'month_num' => $month,
            'month_name' => $monthStart->format('F'),
            'operational_days' => $operationalDays
        ]);

        // Growth calculations
        $priorMonth = MonthlyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $month == 1 ? $year - 1 : $year)
            ->where('month_num', $month == 1 ? 12 : $month - 1)
            ->first();

        if ($priorMonth) {
            $summary['sales_vs_prior_month'] = round($summary['total_sales'] - $priorMonth->total_sales, 2);
            $summary['sales_growth_percent'] = $priorMonth->total_sales > 0
                ? round((($summary['total_sales'] - $priorMonth->total_sales) / $priorMonth->total_sales) * 100, 2)
                : 0;
        }

        $priorYear = MonthlyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $year - 1)
            ->where('month_num', $month)
            ->first();

        if ($priorYear) {
            $summary['sales_vs_same_month_prior_year'] = round($summary['total_sales'] - $priorYear->total_sales, 2);
            $summary['yoy_growth_percent'] = $priorYear->total_sales > 0
                ? round((($summary['total_sales'] - $priorYear->total_sales) / $priorYear->total_sales) * 100, 2)
                : 0;
        }

        MonthlyStoreSummary::updateOrCreate(
            [
                'franchise_store' => $store,
                'year_num' => $year,
                'month_num' => $month
            ],
            $summary
        );
    }

    private function aggregateMonthlyItems(string $store, int $year, int $month): void
    {
        $monthStart = Carbon::create($year, $month, 1);
        $monthEnd = $monthStart->copy()->endOfMonth();

        $items = WeeklyItemSummary::where('franchise_store', $store)
            ->where('year_num', $year)
            ->where(function($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('week_start_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                  ->orWhereBetween('week_end_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            })
            ->selectRaw('
                item_id,
                menu_item_name,
                menu_item_account,
                SUM(quantity_sold) as quantity_sold,
                SUM(gross_sales) as gross_sales,
                SUM(net_sales) as net_sales,
                AVG(avg_item_price) as avg_item_price,
                AVG(avg_daily_quantity) as avg_daily_quantity,
                SUM(delivery_quantity) as delivery_quantity,
                SUM(carryout_quantity) as carryout_quantity
            ')
            ->groupBy('item_id', 'menu_item_name', 'menu_item_account')
            ->get();

        foreach ($items as $item) {
            MonthlyItemSummary::updateOrCreate(
                [
                    'franchise_store' => $store,
                    'year_num' => $year,
                    'month_num' => $month,
                    'item_id' => $item->item_id
                ],
                [
                    'menu_item_name' => $item->menu_item_name,
                    'menu_item_account' => $item->menu_item_account,
                    'quantity_sold' => $item->quantity_sold,
                    'gross_sales' => round($item->gross_sales, 2),
                    'net_sales' => round($item->net_sales, 2),
                    'avg_item_price' => round($item->avg_item_price, 2),
                    'avg_daily_quantity' => round($item->avg_daily_quantity, 2),
                    'delivery_quantity' => $item->delivery_quantity,
                    'carryout_quantity' => $item->carryout_quantity
                ]
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // QUARTERLY AGGREGATION FROM MONTHLY
    // ═══════════════════════════════════════════════════════════════════════════

    public function updateQuarterlySummariesYearQuarter(int $year, int $quarter): void
    {
        $m1 = ($quarter - 1) * 3 + 1;
        $m3 = $quarter * 3;

        $stores = MonthlyStoreSummary::where('year_num', $year)
            ->whereBetween('month_num', [$m1, $m3])
            ->distinct()
            ->pluck('franchise_store');

        foreach ($stores as $store) {
            $this->aggregateQuarterlyStore($store, $year, $quarter);
            $this->aggregateQuarterlyItems($store, $year, $quarter);
        }
    }

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
        $qEnd = Carbon::create($year, $m3, 1)->endOfMonth();

        $summary = $this->sumStorePeriod($monthly, [
            'franchise_store' => $store,
            'year_num' => $year,
            'quarter_num' => $quarter,
            'quarter_start_date' => $qStart->toDateString(),
            'quarter_end_date' => $qEnd->toDateString(),
            'operational_days' => $monthly->sum('operational_days'),
            'operational_months' => $monthly->count()
        ]);

        // Growth calculations
        $priorQuarter = QuarterlyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $quarter == 1 ? $year - 1 : $year)
            ->where('quarter_num', $quarter == 1 ? 4 : $quarter - 1)
            ->first();

        if ($priorQuarter) {
            $summary['sales_vs_prior_quarter'] = round($summary['total_sales'] - $priorQuarter->total_sales, 2);
            $summary['sales_growth_percent'] = $priorQuarter->total_sales > 0
                ? round((($summary['total_sales'] - $priorQuarter->total_sales) / $priorQuarter->total_sales) * 100, 2)
                : 0;
        }

        $priorYear = QuarterlyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $year - 1)
            ->where('quarter_num', $quarter)
            ->first();

        if ($priorYear) {
            $summary['sales_vs_same_quarter_prior_year'] = round($summary['total_sales'] - $priorYear->total_sales, 2);
            $summary['yoy_growth_percent'] = $priorYear->total_sales > 0
                ? round((($summary['total_sales'] - $priorYear->total_sales) / $priorYear->total_sales) * 100, 2)
                : 0;
        }

        QuarterlyStoreSummary::updateOrCreate(
            [
                'franchise_store' => $store,
                'year_num' => $year,
                'quarter_num' => $quarter
            ],
            $summary
        );
    }

    private function aggregateQuarterlyItems(string $store, int $year, int $quarter): void
    {
        $m1 = ($quarter - 1) * 3 + 1;
        $m3 = $quarter * 3;
        $qStart = Carbon::create($year, $m1, 1);
        $qEnd = Carbon::create($year, $m3, 1)->endOfMonth();

        $items = MonthlyItemSummary::where('franchise_store', $store)
            ->where('year_num', $year)
            ->whereBetween('month_num', [$m1, $m3])
            ->selectRaw('
                item_id,
                menu_item_name,
                menu_item_account,
                SUM(quantity_sold) as quantity_sold,
                SUM(gross_sales) as gross_sales,
                SUM(net_sales) as net_sales,
                AVG(avg_item_price) as avg_item_price,
                AVG(avg_daily_quantity) as avg_daily_quantity,
                SUM(delivery_quantity) as delivery_quantity,
                SUM(carryout_quantity) as carryout_quantity
            ')
            ->groupBy('item_id', 'menu_item_name', 'menu_item_account')
            ->get();

        foreach ($items as $item) {
            QuarterlyItemSummary::updateOrCreate(
                [
                    'franchise_store' => $store,
                    'year_num' => $year,
                    'quarter_num' => $quarter,
                    'item_id' => $item->item_id
                ],
                [
                    'menu_item_name' => $item->menu_item_name,
                    'menu_item_account' => $item->menu_item_account,
                    'quantity_sold' => $item->quantity_sold,
                    'gross_sales' => round($item->gross_sales, 2),
                    'net_sales' => round($item->net_sales, 2),
                    'avg_item_price' => round($item->avg_item_price, 2),
                    'avg_daily_quantity' => round($item->avg_daily_quantity, 2),
                    'delivery_quantity' => $item->delivery_quantity,
                    'carryout_quantity' => $item->carryout_quantity,
                    'quarter_start_date' => $qStart->toDateString(),
                    'quarter_end_date' => $qEnd->toDateString()
                ]
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // YEARLY AGGREGATION FROM QUARTERLY
    // ═══════════════════════════════════════════════════════════════════════════

    public function updateYearlySummariesYear(int $year): void
    {
        $stores = QuarterlyStoreSummary::where('year_num', $year)
            ->distinct()
            ->pluck('franchise_store');

        foreach ($stores as $store) {
            $this->aggregateYearlyStore($store, $year);
            $this->aggregateYearlyItems($store, $year);
        }
    }

    private function aggregateYearlyStore(string $store, int $year): void
    {
        $quarterly = QuarterlyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $year)
            ->get();

        if ($quarterly->isEmpty()) {
            return;
        }

        $summary = $this->sumStorePeriod($quarterly, [
            'franchise_store' => $store,
            'year_num' => $year,
            'operational_days' => $quarterly->sum('operational_days'),
            'operational_months' => $quarterly->sum('operational_months')
        ]);

        // Growth calculation
        $priorYear = YearlyStoreSummary::where('franchise_store', $store)
            ->where('year_num', $year - 1)
            ->first();

        if ($priorYear) {
            $summary['sales_vs_prior_year'] = round($summary['total_sales'] - $priorYear->total_sales, 2);
            $summary['sales_growth_percent'] = $priorYear->total_sales > 0
                ? round((($summary['total_sales'] - $priorYear->total_sales) / $priorYear->total_sales) * 100, 2)
                : 0;
        }

        YearlyStoreSummary::updateOrCreate(
            [
                'franchise_store' => $store,
                'year_num' => $year
            ],
            $summary
        );
    }

    private function aggregateYearlyItems(string $store, int $year): void
    {
        $items = QuarterlyItemSummary::where('franchise_store', $store)
            ->where('year_num', $year)
            ->selectRaw('
                item_id,
                menu_item_name,
                menu_item_account,
                SUM(quantity_sold) as quantity_sold,
                SUM(gross_sales) as gross_sales,
                SUM(net_sales) as net_sales,
                AVG(avg_item_price) as avg_item_price,
                AVG(avg_daily_quantity) as avg_daily_quantity,
                SUM(delivery_quantity) as delivery_quantity,
                SUM(carryout_quantity) as carryout_quantity
            ')
            ->groupBy('item_id', 'menu_item_name', 'menu_item_account')
            ->get();

        foreach ($items as $item) {
            YearlyItemSummary::updateOrCreate(
                [
                    'franchise_store' => $store,
                    'year_num' => $year,
                    'item_id' => $item->item_id
                ],
                [
                    'menu_item_name' => $item->menu_item_name,
                    'menu_item_account' => $item->menu_item_account,
                    'quantity_sold' => $item->quantity_sold,
                    'gross_sales' => round($item->gross_sales, 2),
                    'net_sales' => round($item->net_sales, 2),
                    'avg_item_price' => round($item->avg_item_price, 2),
                    'avg_daily_quantity' => round($item->avg_daily_quantity, 2),
                    'delivery_quantity' => $item->delivery_quantity,
                    'carryout_quantity' => $item->carryout_quantity
                ]
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPER METHOD - SUM ALL METRICS
    // ═══════════════════════════════════════════════════════════════════════════

    private function sumStorePeriod($records, array $base): array
    {
        $summary = $base + [
            'total_sales' => round($records->sum('total_sales'), 2),
            'gross_sales' => round($records->sum('gross_sales'), 2),
            'net_sales' => round($records->sum('net_sales'), 2),
            'refund_amount' => round($records->sum('refund_amount'), 2),
            'total_orders' => $records->sum('total_orders'),
            'completed_orders' => $records->sum('completed_orders'),
            'cancelled_orders' => $records->sum('cancelled_orders'),
            'modified_orders' => $records->sum('modified_orders'),
            'refunded_orders' => $records->sum('refunded_orders'),
            'customer_count' => $records->sum('customer_count'),

            // Channels
            'phone_orders' => $records->sum('phone_orders'),
            'phone_sales' => round($records->sum('phone_sales'), 2),
            'website_orders' => $records->sum('website_orders'),
            'website_sales' => round($records->sum('website_sales'), 2),
            'mobile_orders' => $records->sum('mobile_orders'),
            'mobile_sales' => round($records->sum('mobile_sales'), 2),
            'call_center_orders' => $records->sum('call_center_orders'),
            'call_center_sales' => round($records->sum('call_center_sales'), 2),
            'drive_thru_orders' => $records->sum('drive_thru_orders'),
            'drive_thru_sales' => round($records->sum('drive_thru_sales'), 2),

            // Marketplace
            'doordash_orders' => $records->sum('doordash_orders'),
            'doordash_sales' => round($records->sum('doordash_sales'), 2),
            'ubereats_orders' => $records->sum('ubereats_orders'),
            'ubereats_sales' => round($records->sum('ubereats_sales'), 2),
            'grubhub_orders' => $records->sum('grubhub_orders'),
            'grubhub_sales' => round($records->sum('grubhub_sales'), 2),

            // Fulfillment
            'delivery_orders' => $records->sum('delivery_orders'),
            'delivery_sales' => round($records->sum('delivery_sales'), 2),
            'carryout_orders' => $records->sum('carryout_orders'),
            'carryout_sales' => round($records->sum('carryout_sales'), 2),

            // Products
            'pizza_quantity' => $records->sum('pizza_quantity'),
            'pizza_sales' => round($records->sum('pizza_sales'), 2),
            'hnr_quantity' => $records->sum('hnr_quantity'),
            'hnr_sales' => round($records->sum('hnr_sales'), 2),
            'bread_quantity' => $records->sum('bread_quantity'),
            'bread_sales' => round($records->sum('bread_sales'), 2),
            'wings_quantity' => $records->sum('wings_quantity'),
            'wings_sales' => round($records->sum('wings_sales'), 2),
            'beverages_quantity' => $records->sum('beverages_quantity'),
            'beverages_sales' => round($records->sum('beverages_sales'), 2),
            'crazy_puffs_quantity' => $records->sum('crazy_puffs_quantity'),
            'crazy_puffs_sales' => round($records->sum('crazy_puffs_sales'), 2),

            // Financial
            'sales_tax' => round($records->sum('sales_tax'), 2),
            'delivery_fees' => round($records->sum('delivery_fees'), 2),
            'delivery_tips' => round($records->sum('delivery_tips'), 2),
            'store_tips' => round($records->sum('store_tips'), 2),
            'total_tips' => round($records->sum('total_tips'), 2),

            // Payments
            'cash_sales' => round($records->sum('cash_sales'), 2),
            'credit_card_sales' => round($records->sum('credit_card_sales'), 2),
            'prepaid_sales' => round($records->sum('prepaid_sales'), 2),
            'over_short' => round($records->sum('over_short'), 2),

            // Portal
            'portal_eligible_orders' => $records->sum('portal_eligible_orders'),
            'portal_used_orders' => $records->sum('portal_used_orders'),
            'portal_on_time_orders' => $records->sum('portal_on_time_orders'),

            // Waste
            'total_waste_items' => $records->sum('total_waste_items'),
            'total_waste_cost' => round($records->sum('total_waste_cost'), 2),

            // Digital
            'digital_orders' => $records->sum('digital_orders'),
            'digital_sales' => round($records->sum('digital_sales'), 2),
        ];

        // Recalculate averages and rates
        $summary['avg_order_value'] = $summary['total_orders'] > 0
            ? round($summary['total_sales'] / $summary['total_orders'], 2)
            : 0;

        $summary['avg_customers_per_order'] = $summary['total_orders'] > 0
            ? round($summary['customer_count'] / $summary['total_orders'], 2)
            : 0;

        $summary['portal_usage_rate'] = $summary['portal_eligible_orders'] > 0
            ? round(($summary['portal_used_orders'] / $summary['portal_eligible_orders']) * 100, 2)
            : 0;

        $summary['portal_on_time_rate'] = $summary['portal_used_orders'] > 0
            ? round(($summary['portal_on_time_orders'] / $summary['portal_used_orders']) * 100, 2)
            : 0;

        $summary['digital_penetration'] = $summary['total_orders'] > 0
            ? round(($summary['digital_orders'] / $summary['total_orders']) * 100, 2)
            : 0;

        return $summary;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // VALIDATION & DEBUGGING METHODS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * ✅ NEW: Validate hourly aggregation accuracy
     */
    public function validateHourlyAggregation(string $store, Carbon $date): array
    {
        $dateStr = $date->toDateString();

        $hourlyTotal = HourlyStoreSummary::where('franchise_store', $store)
            ->where('business_date', $dateStr)
            ->sum('gross_sales');

        $rawTotal = DetailOrderHot::where('franchise_store', $store)
            ->where('business_date', $dateStr)
            ->sum('gross_sales');

        $diff = abs($hourlyTotal - $rawTotal);
        $isValid = $diff < 0.01; // Allow 1 cent tolerance

        if (!$isValid) {
            Log::error("Hourly aggregation mismatch", [
                'store' => $store,
                'date' => $dateStr,
                'hourly_total' => $hourlyTotal,
                'raw_total' => $rawTotal,
                'difference' => $diff
            ]);
        }

        return [
            'valid' => $isValid,
            'hourly_total' => $hourlyTotal,
            'raw_total' => $rawTotal,
            'difference' => $diff
        ];
    }

    /**
     * ✅ NEW: Get aggregation health status
     */
    public function getAggregationHealth(Carbon $date): array
    {
        $dateStr = $date->toDateString();

        $rawOrders = DetailOrderHot::where('business_date', $dateStr)->count();
        $hourlyRecords = HourlyStoreSummary::where('business_date', $dateStr)->count();
        $dailyRecords = DailyStoreSummary::where('business_date', $dateStr)->count();

        return [
            'date' => $dateStr,
            'raw_orders' => $rawOrders,
            'hourly_records' => $hourlyRecords,
            'daily_records' => $dailyRecords,
            'avg_hours_per_store' => $dailyRecords > 0 ? round($hourlyRecords / $dailyRecords, 1) : 0,
            'status' => $rawOrders > 0 && $hourlyRecords > 0 && $dailyRecords > 0 ? 'healthy' : 'incomplete'
        ];
    }
}
