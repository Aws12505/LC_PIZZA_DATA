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

/**
 * AggregationService - Build and maintain summary tables
 *
 * Creates pre-computed aggregations from raw data to enable
 * ultra-fast reporting without scanning billions of rows.
 *
 * All queries use Laravel Query Builder (no raw SQL) for:
 * - Type safety
 * - IDE auto-completion
 * - Testability
 * - Maintainability
 */
class AggregationService
{
    /**
     * Update all daily summaries for a specific date
     *
     * This is the main entry point called after data import
     *
     * @param Carbon $date Date to aggregate
     */
    public function updateDailySummaries(Carbon $date): void
    {
        Log::info("Updating daily summaries for: " . $date->toDateString());

        // Get all stores that have data for this date
        $stores = DetailOrderHot::where('business_date', $date->toDateString())
            ->distinct()
            ->pluck('franchise_store');

        if ($stores->isEmpty()) {
            Log::warning("No stores found with data for " . $date->toDateString());
            return;
        }

        Log::info("Found " . count($stores) . " stores with data");

        foreach ($stores as $store) {
            try {
                $this->updateDailyStoreSummary($store, $date);
                $this->updateDailyItemSummary($store, $date);
            } catch (\Exception $e) {
                Log::error("Failed to update summaries for store {$store}: " . $e->getMessage());
                // Continue with other stores
            }
        }

        Log::info("Daily summaries updated for " . count($stores) . " stores");
    }

    /**
     * Update daily_store_summary for a specific store and date
     *
     * Aggregates from:
     * - detail_orders_hot (order metrics)
     * - order_line_hot (product metrics)
     * - summary_transactions_hot (payment metrics)
     * - waste_hot (waste metrics)
     * - summary_sales_hot (over/short)
     */
    public function updateDailyStoreSummary(string $store, Carbon $date): void
    {
        Log::debug("Updating daily store summary", [
            'store' => $store,
            'date' => $date->toDateString()
        ]);

        $dateString = $date->toDateString();

        $ordersBase = DetailOrderHot::where('franchise_store', $store)
            ->where('business_date', $dateString);

        if (!$ordersBase->exists()) {
            Log::warning("No order metrics found for {$store} on {$dateString}");
            return;
        }

        // --- Order metrics (no raw SQL, computed via filtered aggregates) ---
        $totalSales = (clone $ordersBase)->sum('gross_sales');
        $grossSales = (clone $ordersBase)->sum('royalty_obligation');

        // net_sales = SUM(gross_sales - non_royalty_amount)
        $netSales = (clone $ordersBase)->get(['gross_sales', 'non_royalty_amount'])
            ->sum(function ($r) {
                return (float) $r->gross_sales - (float) ($r->non_royalty_amount ?? 0);
            });

        $refundAmount = (clone $ordersBase)->where('refunded', 'Yes')->sum('gross_sales');

        $totalOrders = (clone $ordersBase)
            ->distinct()
            ->count('order_id');

        $refundedOrders = (clone $ordersBase)
            ->where('refunded', 'Yes')
            ->distinct()
            ->count('order_id');

        $modifiedOrders = (clone $ordersBase)
            ->whereNotNull('modified_order_amount')
            ->distinct()
            ->count('order_id');

        $customerCount = (clone $ordersBase)->sum('customer_count');

        // Channel metrics by placed method
        $phoneOrders = (clone $ordersBase)->where('order_placed_method', 'Phone')->distinct()->count('order_id');
        $phoneSales  = (clone $ordersBase)->where('order_placed_method', 'Phone')->sum('gross_sales');

        $websiteOrders = (clone $ordersBase)->where('order_placed_method', 'Website')->distinct()->count('order_id');
        $websiteSales  = (clone $ordersBase)->where('order_placed_method', 'Website')->sum('gross_sales');

        $mobileOrders = (clone $ordersBase)->where('order_placed_method', 'Mobile')->distinct()->count('order_id');
        $mobileSales  = (clone $ordersBase)->where('order_placed_method', 'Mobile')->sum('gross_sales');

        $callCenterOrders = (clone $ordersBase)->where('order_placed_method', 'CallCenterAgent')->distinct()->count('order_id');
        $callCenterSales  = (clone $ordersBase)->where('order_placed_method', 'CallCenterAgent')->sum('gross_sales');

        // Marketplace metrics
        $doordashOrders = (clone $ordersBase)->where('order_placed_method', 'DoorDash')->distinct()->count('order_id');
        $doordashSales  = (clone $ordersBase)->where('order_placed_method', 'DoorDash')->sum('gross_sales');

        $ubereatsOrders = (clone $ordersBase)->where('order_placed_method', 'UberEats')->distinct()->count('order_id');
        $ubereatsSales  = (clone $ordersBase)->where('order_placed_method', 'UberEats')->sum('gross_sales');

        $grubhubOrders  = (clone $ordersBase)->where('order_placed_method', 'Grubhub')->distinct()->count('order_id');
        $grubhubSales   = (clone $ordersBase)->where('order_placed_method', 'Grubhub')->sum('gross_sales');

        // Fulfillment metrics
        $deliveryOrders = (clone $ordersBase)->where('order_fulfilled_method', 'Delivery')->distinct()->count('order_id');
        $deliverySales  = (clone $ordersBase)->where('order_fulfilled_method', 'Delivery')->sum('gross_sales');

        $carryoutOrders = (clone $ordersBase)
            ->whereIn('order_fulfilled_method', ['Register', 'Drive-Thru'])
            ->distinct()
            ->count('order_id');

        $carryoutSales = (clone $ordersBase)
            ->whereIn('order_fulfilled_method', ['Register', 'Drive-Thru'])
            ->sum('gross_sales');

        // Financial metrics
        $salesTax     = (clone $ordersBase)->sum('sales_tax');
        $deliveryFees = (clone $ordersBase)->sum('delivery_fee');
        $deliveryTips = (clone $ordersBase)->sum('delivery_tip');
        $storeTips    = (clone $ordersBase)->sum('store_tip_amount');

        // Portal metrics
        $portalEligibleOrders = (clone $ordersBase)->where('portal_eligible', 'Yes')->distinct()->count('order_id');
        $portalUsedOrders     = (clone $ordersBase)->where('portal_used', 'Yes')->distinct()->count('order_id');
        $portalOnTimeOrders   = (clone $ordersBase)->where('put_into_portal_before_promise_time', 'Yes')->distinct()->count('order_id');

        // --- Product metrics (no raw SQL) ---
        $linesBase = OrderLineHot::where('franchise_store', $store)
            ->where('business_date', $dateString);

        $pizzaQuantity   = (clone $linesBase)->where('is_pizza', 1)->sum('quantity');
        $pizzaSales      = (clone $linesBase)->where('is_pizza', 1)->sum('net_amount');

        $breadQuantity   = (clone $linesBase)->where('is_bread', 1)->sum('quantity');
        $breadSales      = (clone $linesBase)->where('is_bread', 1)->sum('net_amount');

        $wingsQuantity   = (clone $linesBase)->where('is_wings', 1)->sum('quantity');
        $wingsSales      = (clone $linesBase)->where('is_wings', 1)->sum('net_amount');

        $beveragesQuantity = (clone $linesBase)->where('is_beverages', 1)->sum('quantity');
        $beveragesSales    = (clone $linesBase)->where('is_beverages', 1)->sum('net_amount');

        $crazyPuffsQuantity = (clone $linesBase)->where('is_crazy_puffs', 1)->sum('quantity');
        $crazyPuffsSales    = (clone $linesBase)->where('is_crazy_puffs', 1)->sum('net_amount');

        // --- Payment metrics (no raw SQL) ---
        $paymentsBase = SummaryTransactionsHot::where('franchise_store', $store)
            ->where('business_date', $dateString);

        $cashSales = (clone $paymentsBase)->where('payment_method', 'Cash')->sum('total_amount');

        $creditCardSales = (clone $paymentsBase)->get(['payment_method', 'total_amount'])
            ->filter(function ($r) {
                $method = (string) $r->payment_method;
                return str_contains($method, 'Credit') || str_contains($method, 'Card');
            })
            ->sum('total_amount');

        $prepaidSales = (clone $paymentsBase)->where('sub_payment_method', 'like', '%Prepaid%')->sum('total_amount');

        // --- Waste metrics (no raw SQL) ---
        $wasteBase = WasteHot::where('franchise_store', $store)
            ->where('business_date', $dateString);

        $totalWasteItems = (clone $wasteBase)->count();

        $totalWasteCost = (clone $wasteBase)->get(['item_cost', 'quantity'])
            ->sum(function ($r) {
                return (float) ($r->item_cost ?? 0) * (float) ($r->quantity ?? 0);
            });

        // --- Over/short ---
        $overShort = SummarySalesHot::where('franchise_store', $store)
            ->where('business_date', $dateString)
            ->value('over_short') ?? 0;

        // Merge all metrics
        $data = [
            'franchise_store' => $store,
            'business_date' => $dateString,

            // Sales metrics
            'total_sales' => $totalSales,
            'gross_sales' => $grossSales,
            'net_sales' => $netSales,
            'refund_amount' => $refundAmount,

            // Order counts
            'total_orders' => $totalOrders,
            'refunded_orders' => $refundedOrders,
            'modified_orders' => $modifiedOrders,
            'customer_count' => $customerCount,

            // Channel metrics
            'phone_orders' => $phoneOrders,
            'phone_sales' => $phoneSales,
            'website_orders' => $websiteOrders,
            'website_sales' => $websiteSales,
            'mobile_orders' => $mobileOrders,
            'mobile_sales' => $mobileSales,
            'call_center_orders' => $callCenterOrders,
            'call_center_sales' => $callCenterSales,

            // Marketplace metrics
            'doordash_orders' => $doordashOrders,
            'doordash_sales' => $doordashSales,
            'ubereats_orders' => $ubereatsOrders,
            'ubereats_sales' => $ubereatsSales,
            'grubhub_orders' => $grubhubOrders,
            'grubhub_sales' => $grubhubSales,

            // Fulfillment metrics
            'delivery_orders' => $deliveryOrders,
            'delivery_sales' => $deliverySales,
            'carryout_orders' => $carryoutOrders,
            'carryout_sales' => $carryoutSales,

            // Financial metrics
            'sales_tax' => $salesTax,
            'delivery_fees' => $deliveryFees,
            'delivery_tips' => $deliveryTips,
            'store_tips' => $storeTips,

            // Portal metrics
            'portal_eligible_orders' => $portalEligibleOrders,
            'portal_used_orders' => $portalUsedOrders,
            'portal_on_time_orders' => $portalOnTimeOrders,

            // Product/category metrics
            'pizza_quantity' => $pizzaQuantity,
            'pizza_sales' => $pizzaSales,
            'bread_quantity' => $breadQuantity,
            'bread_sales' => $breadSales,
            'wings_quantity' => $wingsQuantity,
            'wings_sales' => $wingsSales,
            'beverages_quantity' => $beveragesQuantity,
            'beverages_sales' => $beveragesSales,
            'crazy_puffs_quantity' => $crazyPuffsQuantity,
            'crazy_puffs_sales' => $crazyPuffsSales,

            // Payments
            'cash_sales' => $cashSales,
            'credit_card_sales' => $creditCardSales,
            'prepaid_sales' => $prepaidSales,

            // Waste + over/short
            'total_waste_items' => $totalWasteItems,
            'total_waste_cost' => $totalWasteCost,
            'over_short' => $overShort,
        ];

        // Calculate derived metrics
        $data['avg_order_value'] = $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0;

        // Digital metrics
        $digitalOrders = ($websiteOrders ?? 0) + ($mobileOrders ?? 0);
        $digitalSales  = ($websiteSales ?? 0) + ($mobileSales ?? 0);
        $data['digital_orders'] = $digitalOrders;
        $data['digital_sales'] = $digitalSales;
        $data['digital_penetration'] = $totalOrders > 0
            ? round(($digitalOrders / $totalOrders) * 100, 2)
            : 0;

        // Portal metrics
        $data['portal_usage_rate'] = $portalEligibleOrders > 0
            ? round(($portalUsedOrders / $portalEligibleOrders) * 100, 2)
            : 0;

        $data['portal_on_time_rate'] = $portalEligibleOrders > 0
            ? round(($portalOnTimeOrders / $portalEligibleOrders) * 100, 2)
            : 0;

        $data['total_tips'] = ($deliveryTips ?? 0) + ($storeTips ?? 0);

        // Upsert to daily_store_summary (in analytics database)
        DB::connection('analytics')
            ->table('daily_store_summary')
            ->upsert(
                [$data],
                ['franchise_store', 'business_date'],
                array_keys($data)
            );

        Log::debug("Daily store summary updated", [
            'store' => $store,
            'date' => $dateString,
            'total_sales' => $data['total_sales'] ?? 0,
            'total_orders' => $data['total_orders'] ?? 0
        ]);
    }

    /**
     * Update daily_item_summary for a specific store and date
     */
    public function updateDailyItemSummary(string $store, Carbon $date): void
    {
        $dateString = $date->toDateString();

        $lines = OrderLineHot::where('franchise_store', $store)
            ->where('business_date', $dateString)
            ->get([
                'franchise_store',
                'business_date',
                'item_id',
                'menu_item_name',
                'menu_item_account',
                'quantity',
                'net_amount',
                'modification_reason',
                'order_fulfilled_method',
                'modified_order_amount',
                'refunded',
            ]);

        // Group in PHP to avoid raw groupBy/CASE
        $itemSummaries = $lines->groupBy(function ($r) {
            return implode('|', [
                $r->franchise_store,
                $r->business_date,
                $r->item_id,
                $r->menu_item_name,
                $r->menu_item_account,
            ]);
        });

        foreach ($itemSummaries as $group) {
            $first = $group->first();

            $quantitySold = $group->sum('quantity');
            $grossSales   = $group->sum('net_amount');

            $netSales = $group->filter(function ($r) {
                return is_null($r->modification_reason) || $r->modification_reason === '';
            })->sum('net_amount');

            $deliveryQty = $group->where('order_fulfilled_method', 'Delivery')->sum('quantity');

            $carryoutQty = $group->filter(function ($r) {
                return in_array($r->order_fulfilled_method, ['Register', 'Drive-Thru'], true);
            })->sum('quantity');

            $modifiedQty = $group->filter(function ($r) {
                return !is_null($r->modified_order_amount);
            })->sum('quantity');

            $refundedQty = $group->where('refunded', 'Yes')->sum('quantity');

            $data = [
                'franchise_store' => $first->franchise_store,
                'business_date' => $first->business_date,
                'item_id' => $first->item_id,
                'menu_item_name' => $first->menu_item_name,
                'menu_item_account' => $first->menu_item_account,

                'quantity_sold' => $quantitySold,
                'gross_sales' => $grossSales,
                'net_sales' => $netSales,
                'delivery_quantity' => $deliveryQty,
                'carryout_quantity' => $carryoutQty,
                'modified_quantity' => $modifiedQty,
                'refunded_quantity' => $refundedQty,
            ];

            $data['avg_item_price'] = $quantitySold > 0
                ? round($grossSales / $quantitySold, 2)
                : 0;

            DB::connection('analytics')
                ->table('daily_item_summary')
                ->upsert(
                    [$data],
                    ['franchise_store', 'business_date', 'item_id'],
                    array_keys($data)
                );
        }

        Log::debug("Daily item summaries updated", [
            'store' => $store,
            'date' => $dateString,
            'items' => $itemSummaries->count()
        ]);
    }

    /**
     * Update weekly summaries for a specific week
     */
    public function updateWeeklySummaries(Carbon $date): void
    {
        $weekStart = $date->copy()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();

        Log::info("Updating weekly summaries", [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString()
        ]);

        // Pull daily summaries then aggregate in PHP
        $dailyRows = DB::connection('analytics')
            ->table('daily_store_summary')
            ->whereBetween('business_date', [
                $weekStart->toDateString(),
                $weekEnd->toDateString()
            ])
            ->get();

        $weeklyGroups = $dailyRows->groupBy(function ($r) {
            $d = Carbon::parse($r->business_date);
            return implode('|', [
                $r->franchise_store,
                $d->year,
                $d->isoWeek,
            ]);
        });

        foreach ($weeklyGroups as $group) {
            $first = $group->first();
            $firstDate = Carbon::parse($first->business_date);

            $data = [
                'franchise_store' => $first->franchise_store,
                'year_num' => $firstDate->year,
                'week_num' => $firstDate->isoWeek,
                'week_start_date' => $group->min('business_date'),
                'week_end_date' => $group->max('business_date'),

                'total_sales' => $group->sum('total_sales'),
                'gross_sales' => $group->sum('gross_sales'),
                'net_sales' => $group->sum('net_sales'),
                'total_orders' => $group->sum('total_orders'),
                'customer_count' => $group->sum('customer_count'),

                'pizza_quantity' => $group->sum('pizza_quantity'),
                'pizza_sales' => $group->sum('pizza_sales'),
                'bread_quantity' => $group->sum('bread_quantity'),
                'bread_sales' => $group->sum('bread_sales'),
                'wings_quantity' => $group->sum('wings_quantity'),
                'wings_sales' => $group->sum('wings_sales'),

                'delivery_orders' => $group->sum('delivery_orders'),
                'delivery_sales' => $group->sum('delivery_sales'),
            ];

            $start = Carbon::parse($data['week_start_date']);
            $end = Carbon::parse($data['week_end_date']);
            $days = $start->diffInDays($end) + 1;

            $data['avg_daily_sales'] = $days > 0
                ? round(($data['total_sales'] ?? 0) / $days, 2)
                : 0;

            $data['avg_daily_orders'] = $days > 0
                ? round(($data['total_orders'] ?? 0) / $days, 2)
                : 0;

            DB::connection('analytics')
                ->table('weekly_store_summary')
                ->upsert(
                    [$data],
                    ['franchise_store', 'year_num', 'week_num'],
                    array_keys($data)
                );
        }

        Log::info("Weekly summaries updated", ['count' => $weeklyGroups->count()]);
    }

    /**
     * Update monthly summaries for a specific month
     */
    public function updateMonthlySummaries(Carbon $date): void
    {
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();

        Log::info("Updating monthly summaries", [
            'month' => $date->format('Y-m')
        ]);

        // Pull daily summaries then aggregate in PHP
        $dailyRows = DB::connection('analytics')
            ->table('daily_store_summary')
            ->whereBetween('business_date', [
                $monthStart->toDateString(),
                $monthEnd->toDateString()
            ])
            ->get();

        $monthlyGroups = $dailyRows->groupBy(function ($r) {
            $d = Carbon::parse($r->business_date);
            return implode('|', [
                $r->franchise_store,
                $d->year,
                $d->month,
            ]);
        });

        foreach ($monthlyGroups as $group) {
            $first = $group->first();
            $firstDate = Carbon::parse($first->business_date);

            $operationalDays = $group->pluck('business_date')->unique()->count();

            $data = [
                'franchise_store' => $first->franchise_store,
                'year_num' => $firstDate->year,
                'month_num' => $firstDate->month,
                'month_name' => $firstDate->format('F'),
                'operational_days' => $operationalDays,

                'total_sales' => $group->sum('total_sales'),
                'gross_sales' => $group->sum('gross_sales'),
                'net_sales' => $group->sum('net_sales'),
                'total_orders' => $group->sum('total_orders'),
                'customer_count' => $group->sum('customer_count'),

                'pizza_quantity' => $group->sum('pizza_quantity'),
                'pizza_sales' => $group->sum('pizza_sales'),
                'bread_quantity' => $group->sum('bread_quantity'),
                'bread_sales' => $group->sum('bread_sales'),

                'delivery_orders' => $group->sum('delivery_orders'),
                'delivery_sales' => $group->sum('delivery_sales'),
            ];

            $days = $data['operational_days'] ?? 0;

            $data['avg_daily_sales'] = $days > 0
                ? round(($data['total_sales'] ?? 0) / $days, 2)
                : 0;

            $data['avg_daily_orders'] = $days > 0
                ? round(($data['total_orders'] ?? 0) / $days, 2)
                : 0;

            DB::connection('analytics')
                ->table('monthly_store_summary')
                ->upsert(
                    [$data],
                    ['franchise_store', 'year_num', 'month_num'],
                    array_keys($data)
                );
        }

        Log::info("Monthly summaries updated", ['count' => $monthlyGroups->count()]);
    }
}
