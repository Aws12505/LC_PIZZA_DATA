<?php

namespace App\Services\Analytics;

use App\Models\Aggregation\{
    HourlyStoreSummary,
    HourlyItemSummary,
    DailyStoreSummary,
    DailyItemSummary,
    WeeklyStoreSummary,
    WeeklyItemSummary,
    MonthlyStoreSummary,
    MonthlyItemSummary,
    QuarterlyStoreSummary,
    QuarterlyItemSummary,
    YearlyStoreSummary,
    YearlyItemSummary
};
use Carbon\Carbon;

/**
 * SummaryQueryService
 * 
 * Optimized query service that intelligently selects the best summary table
 * based on date range for maximum performance.
 * 
 * Replicates ALL queries from LogicsAndQueriesServices.php but using 
 * pre-aggregated summary tables instead of raw data.
 */
class SummaryQueryService
{
    /**
     * Smart table selection based on date range
     */
    private function getOptimalStoreSummaryModel(Carbon $startDate, Carbon $endDate): string
    {
        $days = $startDate->diffInDays($endDate);

        if ($days === 0) {
            return HourlyStoreSummary::class;
        } elseif ($days <= 6) {
            return DailyStoreSummary::class;
        } elseif ($days <= 27) {
            return WeeklyStoreSummary::class;
        } elseif ($days <= 89) {
            return MonthlyStoreSummary::class;
        } elseif ($days <= 364) {
            return QuarterlyStoreSummary::class;
        } else {
            return YearlyStoreSummary::class;
        }
    }

    private function getOptimalItemSummaryModel(Carbon $startDate, Carbon $endDate): string
    {
        $days = $startDate->diffInDays($endDate);

        if ($days === 0) {
            return HourlyItemSummary::class;
        } elseif ($days <= 6) {
            return DailyItemSummary::class;
        } elseif ($days <= 27) {
            return WeeklyItemSummary::class;
        } elseif ($days <= 89) {
            return MonthlyItemSummary::class;
        } elseif ($days <= 364) {
            return QuarterlyItemSummary::class;
        } else {
            return YearlyItemSummary::class;
        }
    }

    /**
     * Get base query for store summaries
     */
    private function getStoreSummaryQuery(string $store, Carbon $startDate, Carbon $endDate)
    {
        $model = $this->getOptimalStoreSummaryModel($startDate, $endDate);

        $query = $model::where('franchise_store', $store);

        // Apply date range filter based on model type
        if ($model === HourlyStoreSummary::class || $model === DailyStoreSummary::class) {
            $query->whereBetween('business_date', [$startDate->toDateString(), $endDate->toDateString()]);
        } elseif ($model === WeeklyStoreSummary::class) {
            $query->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('week_start_date', [$startDate->toDateString(), $endDate->toDateString()])
                  ->orWhereBetween('week_end_date', [$startDate->toDateString(), $endDate->toDateString()]);
            });
        } elseif ($model === MonthlyStoreSummary::class) {
            $query->where('year_num', '>=', $startDate->year)
                  ->where('year_num', '<=', $endDate->year)
                  ->where('month_num', '>=', $startDate->month)
                  ->where('month_num', '<=', $endDate->month);
        } elseif ($model === QuarterlyStoreSummary::class) {
            $query->where('year_num', '>=', $startDate->year)
                  ->where('year_num', '<=', $endDate->year)
                  ->where('quarter_num', '>=', $startDate->quarter)
                  ->where('quarter_num', '<=', $endDate->quarter);
        } else {
            $query->where('year_num', '>=', $startDate->year)
                  ->where('year_num', '<=', $endDate->year);
        }

        return $query;
    }

    /**
     * Get base query for item summaries
     */
    private function getItemSummaryQuery(string $store, Carbon $startDate, Carbon $endDate)
    {
        $model = $this->getOptimalItemSummaryModel($startDate, $endDate);

        $query = $model::where('franchise_store', $store);

        // Apply date range filter based on model type
        if ($model === HourlyItemSummary::class || $model === DailyItemSummary::class) {
            $query->whereBetween('business_date', [$startDate->toDateString(), $endDate->toDateString()]);
        } elseif ($model === WeeklyItemSummary::class) {
            $query->where('year_num', '>=', $startDate->year)
                  ->where('year_num', '<=', $endDate->year)
                  ->where('week_num', '>=', $startDate->week)
                  ->where('week_num', '<=', $endDate->week);
        } elseif ($model === MonthlyItemSummary::class) {
            $query->where('year_num', '>=', $startDate->year)
                  ->where('year_num', '<=', $endDate->year)
                  ->where('month_num', '>=', $startDate->month)
                  ->where('month_num', '<=', $endDate->month);
        } elseif ($model === QuarterlyItemSummary::class) {
            $query->where('year_num', '>=', $startDate->year)
                  ->where('year_num', '<=', $endDate->year)
                  ->where('quarter_num', '>=', $startDate->quarter)
                  ->where('quarter_num', '<=', $endDate->quarter);
        } else {
            $query->where('year_num', '>=', $startDate->year)
                  ->where('year_num', '<=', $endDate->year);
        }

        return $query;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SALES METRICS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Get total sales (royalty_obligation equivalent)
     * OLD: sum(royalty_obligation) from detail_order_hot
     * NEW: sum(total_sales) from summary tables
     */
    public function getSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('total_sales'), 2);
    }

    /**
     * Get gross sales
     * OLD: sum(gross_sales) from detail_order_hot
     * NEW: sum(gross_sales) from summary tables
     */
    public function getGrossSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('gross_sales'), 2);
    }

    /**
     * Get net sales
     */
    public function getNetSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('net_sales'), 2);
    }

    /**
     * Get refund amount
     */
    public function getRefundAmount(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('refund_amount'), 2);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ORDER METRICS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Get total orders
     * OLD: count(distinct order_id) from detail_order_hot
     * NEW: sum(total_orders) from summary tables
     */
    public function getOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('total_orders');
    }

    /**
     * Get completed orders
     */
    public function getCompletedOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('completed_orders');
    }

    /**
     * Get cancelled orders
     */
    public function getCancelledOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('cancelled_orders');
    }

    /**
     * Get modified orders
     * OLD: count WHERE override_approval_employee IS NOT NULL
     * NEW: sum(modified_orders) from summary tables
     */
    public function getModifiedOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('modified_orders');
    }

    /**
     * Get refunded orders
     */
    public function getRefundedOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('refunded_orders');
    }

    /**
     * Get average order value
     */
    public function getAvgOrderValue(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $orders = $this->getOrders($store, $startDate, $endDate);
        $sales = $this->getSales($store, $startDate, $endDate);
        return $orders > 0 ? round($sales / $orders, 2) : 0;
    }

    /**
     * Get customer count
     * OLD: sum(customer_count) from detail_order_hot
     * NEW: sum(customer_count) from summary tables
     */
    public function getCustomerCount(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('customer_count');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // CHANNEL METRICS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Get phone sales
     * OLD: sum(royalty_obligation) WHERE order_placed_method = 'Phone'
     * NEW: sum(phone_sales) from summary tables
     */
    public function getPhoneSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('phone_sales'), 2);
    }

    public function getPhoneOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('phone_orders');
    }

    /**
     * Get website sales
     * OLD: sum(royalty_obligation) WHERE order_placed_method = 'Website'
     * NEW: sum(website_sales) from summary tables
     */
    public function getWebsiteSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('website_sales'), 2);
    }

    public function getWebsiteOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('website_orders');
    }

    /**
     * Get mobile sales
     * OLD: sum(royalty_obligation) WHERE order_placed_method = 'Mobile'
     * NEW: sum(mobile_sales) from summary tables
     */
    public function getMobileSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('mobile_sales'), 2);
    }

    public function getMobileOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('mobile_orders');
    }

    /**
     * Get call center sales (SoundHoundAgent)
     * OLD: sum(royalty_obligation) WHERE order_placed_method = 'SoundHoundAgent'
     * NEW: sum(call_center_sales) from summary tables
     */
    public function getCallCenterSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('call_center_sales'), 2);
    }

    public function getCallCenterOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('call_center_orders');
    }

    /**
     * Get drive thru sales
     * OLD: sum(royalty_obligation) WHERE order_placed_method = 'Drive Thru'
     * NEW: sum(drive_thru_sales) from summary tables
     */
    public function getDriveThruSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('drive_thru_sales'), 2);
    }

    public function getDriveThruOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('drive_thru_orders');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // MARKETPLACE METRICS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Get DoorDash sales
     * OLD: sum(royalty_obligation) WHERE order_placed_method = 'DoorDash'
     * NEW: sum(doordash_sales) from summary tables
     */
    public function getDoordashSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('doordash_sales'), 2);
    }

    public function getDoordashOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('doordash_orders');
    }

    /**
     * Get UberEats sales
     */
    public function getUbereatsSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('ubereats_sales'), 2);
    }

    public function getUbereatsOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('ubereats_orders');
    }

    /**
     * Get Grubhub sales
     */
    public function getGrubhubSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('grubhub_sales'), 2);
    }

    public function getGrubhubOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('grubhub_orders');
    }

    /**
     * Third Party Marketplace Summary
     * Replicates: ThirdPartyMarketplace() from LogicsAndQueriesServices
     */
    public function getThirdPartyMarketplace(string $store, Carbon $startDate, Carbon $endDate): array
    {
        return [
            'franchise_store' => $store,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),

            // DoorDash
            'doordash_product_costs' => $this->getDoordashSales($store, $startDate, $endDate),
            'doordash_orders' => $this->getDoordashOrders($store, $startDate, $endDate),

            // UberEats
            'ubereats_product_costs' => $this->getUbereatsSales($store, $startDate, $endDate),
            'ubereats_orders' => $this->getUbereatsOrders($store, $startDate, $endDate),

            // Grubhub
            'grubhub_product_costs' => $this->getGrubhubSales($store, $startDate, $endDate),
            'grubhub_orders' => $this->getGrubhubOrders($store, $startDate, $endDate),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FULFILLMENT METRICS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Get delivery sales
     * OLD: sum(royalty_obligation) WHERE order_fulfilled_method = 'Delivery'
     * NEW: sum(delivery_sales) from summary tables
     */
    public function getDeliverySales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('delivery_sales'), 2);
    }

    public function getDeliveryOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('delivery_orders');
    }

    /**
     * Get carryout sales
     * OLD: sum(royalty_obligation) WHERE order_fulfilled_method IN ('Register', 'Drive-Thru')
     * NEW: sum(carryout_sales) from summary tables
     */
    public function getCarryoutSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('carryout_sales'), 2);
    }

    public function getCarryoutOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('carryout_orders');
    }

    /**
     * Delivery Order Summary
     * Replicates: DeliveryOrderSummary() from LogicsAndQueriesServices
     */
    public function getDeliveryOrderSummary(string $store, Carbon $startDate, Carbon $endDate): array
    {
        $data = $this->getStoreSummaryQuery($store, $startDate, $endDate)->get();

        $deliveryOrders = $data->sum('delivery_orders');
        $deliverySales = $data->sum('delivery_sales');
        $deliveryFees = $data->sum('delivery_fees');
        $deliveryTips = $data->sum('delivery_tips');
        $salesTax = $data->sum('sales_tax');

        return [
            'franchise_store' => $store,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'orders_count' => (int) $deliveryOrders,
            'product_cost' => round($deliverySales, 2),
            'tax' => round($salesTax, 2),
            'delivery_charges' => round($deliveryFees, 2),
            'tip' => round($deliveryTips, 2),
            'order_total' => round($deliverySales + $salesTax + $deliveryFees + $deliveryTips, 2),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PRODUCT CATEGORY METRICS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Get pizza sales
     * OLD: sum from order_line_hot WHERE menu_item_account = 'Pizza'
     * NEW: sum(pizza_sales) from summary tables
     */
    public function getPizzaSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('pizza_sales'), 2);
    }

    public function getPizzaQuantity(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('pizza_quantity');
    }

    /**
     * Get HNR (Hot-N-Ready) sales
     */
    public function getHnrSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('hnr_sales'), 2);
    }

    public function getHnrQuantity(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('hnr_quantity');
    }

    /**
     * Get bread sales
     */
    public function getBreadSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('bread_sales'), 2);
    }

    public function getBreadQuantity(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('bread_quantity');
    }

    /**
     * Get wings sales
     */
    public function getWingsSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('wings_sales'), 2);
    }

    public function getWingsQuantity(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('wings_quantity');
    }

    /**
     * Get beverages sales
     */
    public function getBeveragesSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('beverages_sales'), 2);
    }

    public function getBeveragesQuantity(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('beverages_quantity');
    }

    /**
     * Get crazy puffs sales
     */
    public function getCrazyPuffsSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('crazy_puffs_sales'), 2);
    }

    public function getCrazyPuffsQuantity(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('crazy_puffs_quantity');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FINANCIAL METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getSalesTax(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('sales_tax'), 2);
    }

    public function getDeliveryFees(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('delivery_fees'), 2);
    }

    public function getDeliveryTips(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('delivery_tips'), 2);
    }

    public function getStoreTips(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('store_tips'), 2);
    }

    public function getTotalTips(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('total_tips'), 2);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PAYMENT METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getCashSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('cash_sales'), 2);
    }

    public function getCreditCardSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('credit_card_sales'), 2);
    }

    public function getPrepaidSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('prepaid_sales'), 2);
    }

    public function getOverShort(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('over_short'), 2);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PORTAL METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getPortalEligibleOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('portal_eligible_orders');
    }

    public function getPortalUsedOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('portal_used_orders');
    }

    public function getPortalUsageRate(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $eligible = $this->getPortalEligibleOrders($store, $startDate, $endDate);
        $used = $this->getPortalUsedOrders($store, $startDate, $endDate);
        return $eligible > 0 ? round(($used / $eligible) * 100, 2) : 0;
    }

    public function getPortalOnTimeOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('portal_on_time_orders');
    }

    public function getPortalOnTimeRate(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $used = $this->getPortalUsedOrders($store, $startDate, $endDate);
        $onTime = $this->getPortalOnTimeOrders($store, $startDate, $endDate);
        return $used > 0 ? round(($onTime / $used) * 100, 2) : 0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // WASTE METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getTotalWasteItems(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('total_waste_items');
    }

    public function getTotalWasteCost(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('total_waste_cost'), 2);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DIGITAL METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getDigitalOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        return (int) $this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('digital_orders');
    }

    public function getDigitalSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        return round($this->getStoreSummaryQuery($store, $startDate, $endDate)->sum('digital_sales'), 2);
    }

    public function getDigitalPenetration(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $totalSales = $this->getSales($store, $startDate, $endDate);
        $digitalSales = $this->getDigitalSales($store, $startDate, $endDate);
        return $totalSales > 0 ? round(($digitalSales / $totalSales) * 100, 2) : 0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ITEM-LEVEL QUERIES
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Get specific item sales
     * OLD: Query order_line_hot WHERE item_id = X
     * NEW: Query *_item_summary WHERE item_id = X
     */
    public function getItemSales(string $store, Carbon $startDate, Carbon $endDate, string $itemId): float
    {
        return round(
            $this->getItemSummaryQuery($store, $startDate, $endDate)
                ->where('item_id', $itemId)
                ->sum('gross_sales'),
            2
        );
    }

    public function getItemQuantity(string $store, Carbon $startDate, Carbon $endDate, string $itemId): int
    {
        return (int) $this->getItemSummaryQuery($store, $startDate, $endDate)
            ->where('item_id', $itemId)
            ->sum('quantity_sold');
    }

    /**
     * Get items by name
     */
    public function getItemsByName(string $store, Carbon $startDate, Carbon $endDate, string $itemName): array
    {
        $items = $this->getItemSummaryQuery($store, $startDate, $endDate)
            ->where('menu_item_name', $itemName)
            ->get();

        return [
            'item_name' => $itemName,
            'quantity_sold' => $items->sum('quantity_sold'),
            'gross_sales' => round($items->sum('gross_sales'), 2),
            'net_sales' => round($items->sum('net_sales'), 2),
            'avg_price' => $items->count() > 0 ? round($items->avg('avg_item_price'), 2) : 0,
        ];
    }

    /**
     * Bread Boost Analysis
     * Replicates: BreadBoost() from LogicsAndQueriesServices
     * 
     * Tracks how many Classic pizza orders included Crazy Bread
     */
    public function getBreadBoost(string $store, Carbon $startDate, Carbon $endDate): array
    {
        // Get all orders with Classic Pepperoni or Classic Cheese
        $classicPizzaQty = $this->getItemSummaryQuery($store, $startDate, $endDate)
            ->whereIn('menu_item_name', ['Classic Pepperoni', 'Classic Cheese'])
            ->sum('quantity_sold');

        // Get Crazy Bread quantity
        $crazyBreadQty = $this->getItemSummaryQuery($store, $startDate, $endDate)
            ->where('menu_item_name', 'Crazy Bread')
            ->sum('quantity_sold');

        // Get other pizza quantity (excluding specific item IDs from old service)
        $excludedIds = [-1,6,7,8,9,101001,101002,101288,103044,202901,101289,204100,204200];
        $otherPizzaQty = $this->getItemSummaryQuery($store, $startDate, $endDate)
            ->whereNotIn('item_id', $excludedIds)
            ->where('menu_item_account', 'Pizza')
            ->whereNotIn('menu_item_name', ['Classic Pepperoni', 'Classic Cheese'])
            ->sum('quantity_sold');

        // Estimate bread attachment (simplified without order-level tracking)
        $estimatedClassicWithBread = min($classicPizzaQty, $crazyBreadQty);
        $remainingBread = max(0, $crazyBreadQty - $estimatedClassicWithBread);
        $estimatedOtherWithBread = min($otherPizzaQty, $remainingBread);

        return [
            'franchise_store' => $store,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'classic_orders' => (int) $classicPizzaQty,
            'classic_with_bread' => (int) $estimatedClassicWithBread,
            'classic_attach_rate' => $classicPizzaQty > 0 ? round(($estimatedClassicWithBread / $classicPizzaQty) * 100, 2) : 0,
            'other_pizza_orders' => (int) $otherPizzaQty,
            'other_with_bread' => (int) $estimatedOtherWithBread,
            'other_attach_rate' => $otherPizzaQty > 0 ? round(($estimatedOtherWithBread / $otherPizzaQty) * 100, 2) : 0,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // COMPREHENSIVE SUMMARY METHODS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Get complete channel data breakdown
     * Replicates: ChannelData() from LogicsAndQueriesServices
     */
    public function getChannelData(string $store, Carbon $startDate, Carbon $endDate): array
    {
        return [
            'franchise_store' => $store,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),

            // Phone
            'phone_orders' => $this->getPhoneOrders($store, $startDate, $endDate),
            'phone_sales' => $this->getPhoneSales($store, $startDate, $endDate),

            // Website
            'website_orders' => $this->getWebsiteOrders($store, $startDate, $endDate),
            'website_sales' => $this->getWebsiteSales($store, $startDate, $endDate),

            // Mobile
            'mobile_orders' => $this->getMobileOrders($store, $startDate, $endDate),
            'mobile_sales' => $this->getMobileSales($store, $startDate, $endDate),

            // Call Center
            'call_center_orders' => $this->getCallCenterOrders($store, $startDate, $endDate),
            'call_center_sales' => $this->getCallCenterSales($store, $startDate, $endDate),

            // Drive Thru
            'drive_thru_orders' => $this->getDriveThruOrders($store, $startDate, $endDate),
            'drive_thru_sales' => $this->getDriveThruSales($store, $startDate, $endDate),

            // Marketplace
            'doordash_orders' => $this->getDoordashOrders($store, $startDate, $endDate),
            'doordash_sales' => $this->getDoordashSales($store, $startDate, $endDate),
            'ubereats_orders' => $this->getUbereatsOrders($store, $startDate, $endDate),
            'ubereats_sales' => $this->getUbereatsSales($store, $startDate, $endDate),
            'grubhub_orders' => $this->getGrubhubOrders($store, $startDate, $endDate),
            'grubhub_sales' => $this->getGrubhubSales($store, $startDate, $endDate),

            // Fulfillment
            'delivery_orders' => $this->getDeliveryOrders($store, $startDate, $endDate),
            'delivery_sales' => $this->getDeliverySales($store, $startDate, $endDate),
            'carryout_orders' => $this->getCarryoutOrders($store, $startDate, $endDate),
            'carryout_sales' => $this->getCarryoutSales($store, $startDate, $endDate),
        ];
    }

    /**
     * Get final summaries
     * Replicates: FinalSummaries() from LogicsAndQueriesServices
     */
    public function getFinalSummaries(string $store, Carbon $startDate, Carbon $endDate): array
    {
        $totalSales = $this->getSales($store, $startDate, $endDate);
        $digitalSales = $this->getDigitalSales($store, $startDate, $endDate);

        return [
            'franchise_store' => $store,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),

            // Sales
            'total_sales' => $totalSales,
            'gross_sales' => $this->getGrossSales($store, $startDate, $endDate),
            'net_sales' => $this->getNetSales($store, $startDate, $endDate),

            // Orders
            'total_orders' => $this->getOrders($store, $startDate, $endDate),
            'modified_orders' => $this->getModifiedOrders($store, $startDate, $endDate),
            'refunded_orders' => $this->getRefundedOrders($store, $startDate, $endDate),
            'customer_count' => $this->getCustomerCount($store, $startDate, $endDate),

            // Channel breakdown
            'phone_sales' => $this->getPhoneSales($store, $startDate, $endDate),
            'call_center_sales' => $this->getCallCenterSales($store, $startDate, $endDate),
            'drive_thru_sales' => $this->getDriveThruSales($store, $startDate, $endDate),
            'website_sales' => $this->getWebsiteSales($store, $startDate, $endDate),
            'mobile_sales' => $this->getMobileSales($store, $startDate, $endDate),

            // Marketplace
            'doordash_sales' => $this->getDoordashSales($store, $startDate, $endDate),
            'grubhub_sales' => $this->getGrubhubSales($store, $startDate, $endDate),
            'ubereats_sales' => $this->getUbereatsSales($store, $startDate, $endDate),

            // Fulfillment
            'delivery_sales' => $this->getDeliverySales($store, $startDate, $endDate),
            'carryout_sales' => $this->getCarryoutSales($store, $startDate, $endDate),

            // Digital
            'digital_sales' => $digitalSales,
            'digital_sales_percent' => $totalSales > 0 ? round(($digitalSales / $totalSales) * 100, 2) : 0,

            // Portal
            'portal_transactions' => $this->getPortalEligibleOrders($store, $startDate, $endDate),
            'put_into_portal' => $this->getPortalUsedOrders($store, $startDate, $endDate),
            'portal_used_percent' => $this->getPortalUsageRate($store, $startDate, $endDate),
            'put_in_portal_on_time' => $this->getPortalOnTimeOrders($store, $startDate, $endDate),
            'in_portal_on_time_percent' => $this->getPortalOnTimeRate($store, $startDate, $endDate),

            // Tips
            'delivery_tips' => $this->getDeliveryTips($store, $startDate, $endDate),
            'store_tips' => $this->getStoreTips($store, $startDate, $endDate),
            'total_tips' => $this->getTotalTips($store, $startDate, $endDate),

            // Financial
            'over_short' => $this->getOverShort($store, $startDate, $endDate),
            'cash_sales' => $this->getCashSales($store, $startDate, $endDate),

            // Waste
            'total_waste_cost' => $this->getTotalWasteCost($store, $startDate, $endDate),
        ];
    }

    /**
     * Get all product category sales
     */
    public function getProductCategorySales(string $store, Carbon $startDate, Carbon $endDate): array
    {
        return [
            'franchise_store' => $store,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),

            'pizza_quantity' => $this->getPizzaQuantity($store, $startDate, $endDate),
            'pizza_sales' => $this->getPizzaSales($store, $startDate, $endDate),

            'hnr_quantity' => $this->getHnrQuantity($store, $startDate, $endDate),
            'hnr_sales' => $this->getHnrSales($store, $startDate, $endDate),

            'bread_quantity' => $this->getBreadQuantity($store, $startDate, $endDate),
            'bread_sales' => $this->getBreadSales($store, $startDate, $endDate),

            'wings_quantity' => $this->getWingsQuantity($store, $startDate, $endDate),
            'wings_sales' => $this->getWingsSales($store, $startDate, $endDate),

            'beverages_quantity' => $this->getBeveragesQuantity($store, $startDate, $endDate),
            'beverages_sales' => $this->getBeveragesSales($store, $startDate, $endDate),

            'crazy_puffs_quantity' => $this->getCrazyPuffsQuantity($store, $startDate, $endDate),
            'crazy_puffs_sales' => $this->getCrazyPuffsSales($store, $startDate, $endDate),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // UTILITY METHODS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Get table info for debugging/logging
     */
    public function getTableInfo(Carbon $startDate, Carbon $endDate): array
    {
        $storeModel = $this->getOptimalStoreSummaryModel($startDate, $endDate);
        $itemModel = $this->getOptimalItemSummaryModel($startDate, $endDate);

        return [
            'date_range_days' => $startDate->diffInDays($endDate),
            'store_summary_table' => class_basename($storeModel),
            'item_summary_table' => class_basename($itemModel),
        ];
    }
}
