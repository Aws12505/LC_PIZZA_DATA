<?php

namespace App\Services\Analytics;

use App\Services\Aggregation\IntelligentAggregationService;
use Carbon\Carbon;

/**
 * SummaryQueryService
 *
 * Now delegates all data access to IntelligentAggregationService,
 * which builds the optimal multi-granularity query plan.
 */
class SummaryQueryService
{
    private IntelligentAggregationService $agg;

    public function __construct(IntelligentAggregationService $agg)
    {
        $this->agg = $agg;
    }

    /**
     * Helper: base request array for store-level metrics.
     */
    private function baseStoreRequest(string $store, Carbon $startDate, Carbon $endDate, array $metrics): array
    {
        return [
            'start_date'   => $startDate->toDateString(),
            'end_date'     => $endDate->toDateString(),
            'summary_type' => 'store',
            'metrics'      => $metrics,
            'filters'      => ['franchise_store' => $store],
        ];
    }

    /**
     * Helper: base request array for item-level metrics.
     */
    private function baseItemRequest(string $store, Carbon $startDate, Carbon $endDate, array $metrics, array $extraFilters = []): array
    {
        $filters = array_merge(['franchise_store' => $store], $extraFilters);

        return [
            'start_date'   => $startDate->toDateString(),
            'end_date'     => $endDate->toDateString(),
            'summary_type' => 'item',
            'metrics'      => $metrics,
            'filters'      => $filters,
        ];
    }

    /**
     * Helper: run aggregation and return first row (or empty array).
     */
    private function fetchSingle(array $request): array
    {
        $result = $this->agg->fetchAggregatedData($request);
        return $result['data'][0] ?? [];
    }

    /**
     * Helper: run aggregation and return array of rows.
     */
    private function fetchAll(array $request): array
    {
        $result = $this->agg->fetchAggregatedData($request);
        return $result['data'] ?? [];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SALES METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['royalty_obligation'])  // ✅ Changed
        );
        return round((float)($row['royalty_obligation'] ?? 0), 2);
    }


    public function getGrossSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['gross_sales'])
        );

        return round((float)($row['gross_sales'] ?? 0), 2);
    }

    public function getNetSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['net_sales'])
        );

        return round((float)($row['net_sales'] ?? 0), 2);
    }

    public function getRefundAmount(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['refund_amount'])
        );

        return round((float)($row['refund_amount'] ?? 0), 2);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ORDER METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['total_orders'])
        );

        return (int)($row['total_orders'] ?? 0);
    }

    public function getCompletedOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['completed_orders'])
        );

        return (int)($row['completed_orders'] ?? 0);
    }

    public function getCancelledOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['cancelled_orders'])
        );

        return (int)($row['cancelled_orders'] ?? 0);
    }

    public function getModifiedOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['modified_orders'])
        );

        return (int)($row['modified_orders'] ?? 0);
    }

    public function getRefundedOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['refunded_orders'])
        );

        return (int)($row['refunded_orders'] ?? 0);
    }

    public function getAvgOrderValue(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $orders = $this->getOrders($store, $startDate, $endDate);
        $sales  = $this->getSales($store, $startDate, $endDate);

        return $orders > 0 ? round($sales / $orders, 2) : 0.0;
    }

    public function getCustomerCount(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['customer_count'])
        );

        return (int)($row['customer_count'] ?? 0);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // CHANNEL METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getPhoneSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['phone_sales'])
        );

        return round((float)($row['phone_sales'] ?? 0), 2);
    }

    public function getPhoneOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['phone_orders'])
        );

        return (int)($row['phone_orders'] ?? 0);
    }

    public function getWebsiteSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['website_sales'])
        );

        return round((float)($row['website_sales'] ?? 0), 2);
    }

    public function getWebsiteOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['website_orders'])
        );

        return (int)($row['website_orders'] ?? 0);
    }

    public function getMobileSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['mobile_sales'])
        );

        return round((float)($row['mobile_sales'] ?? 0), 2);
    }

    public function getMobileOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['mobile_orders'])
        );

        return (int)($row['mobile_orders'] ?? 0);
    }

    public function getCallCenterSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['call_center_sales'])
        );

        return round((float)($row['call_center_sales'] ?? 0), 2);
    }

    public function getCallCenterOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['call_center_orders'])
        );

        return (int)($row['call_center_orders'] ?? 0);
    }

    public function getDriveThruSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['drive_thru_sales'])
        );

        return round((float)($row['drive_thru_sales'] ?? 0), 2);
    }

    public function getDriveThruOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['drive_thru_orders'])
        );

        return (int)($row['drive_thru_orders'] ?? 0);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // MARKETPLACE METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getDoordashSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['doordash_sales'])
        );

        return round((float)($row['doordash_sales'] ?? 0), 2);
    }

    public function getDoordashOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['doordash_orders'])
        );

        return (int)($row['doordash_orders'] ?? 0);
    }

    public function getUbereatsSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['ubereats_sales'])
        );

        return round((float)($row['ubereats_sales'] ?? 0), 2);
    }

    public function getUbereatsOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['ubereats_orders'])
        );

        return (int)($row['ubereats_orders'] ?? 0);
    }

    public function getGrubhubSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['grubhub_sales'])
        );

        return round((float)($row['grubhub_sales'] ?? 0), 2);
    }

    public function getGrubhubOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['grubhub_orders'])
        );

        return (int)($row['grubhub_orders'] ?? 0);
    }

    public function getThirdPartyMarketplace(string $store, Carbon $startDate, Carbon $endDate): array
    {
        return [
            'franchise_store'         => $store,
            'start_date'              => $startDate->toDateString(),
            'end_date'                => $endDate->toDateString(),
            'doordash_product_costs'  => $this->getDoordashSales($store, $startDate, $endDate),
            'doordash_orders'         => $this->getDoordashOrders($store, $startDate, $endDate),
            'ubereats_product_costs'  => $this->getUbereatsSales($store, $startDate, $endDate),
            'ubereats_orders'         => $this->getUbereatsOrders($store, $startDate, $endDate),
            'grubhub_product_costs'   => $this->getGrubhubSales($store, $startDate, $endDate),
            'grubhub_orders'          => $this->getGrubhubOrders($store, $startDate, $endDate),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FULFILLMENT METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getDeliverySales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['delivery_sales'])
        );

        return round((float)($row['delivery_sales'] ?? 0), 2);
    }

    public function getDeliveryOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['delivery_orders'])
        );

        return (int)($row['delivery_orders'] ?? 0);
    }

    public function getCarryoutSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['carryout_sales'])
        );

        return round((float)($row['carryout_sales'] ?? 0), 2);
    }

    public function getCarryoutOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['carryout_orders'])
        );

        return (int)($row['carryout_orders'] ?? 0);
    }

    public function getDeliveryOrderSummary(string $store, Carbon $startDate, Carbon $endDate): array
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, [
                'delivery_orders',
                'delivery_sales',
                'delivery_fees',
                'delivery_tips',
                'sales_tax',
            ])
        );

        $deliveryOrders = (int)($row['delivery_orders'] ?? 0);
        $deliverySales  = (float)($row['delivery_sales'] ?? 0);
        $deliveryFees   = (float)($row['delivery_fees'] ?? 0);
        $deliveryTips   = (float)($row['delivery_tips'] ?? 0);
        $salesTax       = (float)($row['sales_tax'] ?? 0);

        return [
            'franchise_store'   => $store,
            'start_date'        => $startDate->toDateString(),
            'end_date'          => $endDate->toDateString(),
            'orders_count'      => $deliveryOrders,
            'product_cost'      => round($deliverySales, 2),
            'tax'               => round($salesTax, 2),
            'delivery_charges'  => round($deliveryFees, 2),
            'tip'               => round($deliveryTips, 2),
            'order_total'       => round($deliverySales + $salesTax + $deliveryFees + $deliveryTips, 2),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PRODUCT CATEGORY METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getPizzaSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, [
                'pizza_delivery_sales',
                'pizza_carryout_sales'
            ])
        );

        $deliverySales = (float)($row['pizza_delivery_sales'] ?? 0);
        $carryoutSales = (float)($row['pizza_carryout_sales'] ?? 0);

        return round($deliverySales + $carryoutSales, 2);
    }

    public function getPizzaQuantity(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, [
                'pizza_delivery_quantity',
                'pizza_carryout_quantity'
            ])
        );

        $deliveryQty = (int)($row['pizza_delivery_quantity'] ?? 0);
        $carryoutQty = (int)($row['pizza_carryout_quantity'] ?? 0);

        return $deliveryQty + $carryoutQty;
    }

    public function getHnrSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, [
                'hnr_delivery_sales',
                'hnr_carryout_sales'
            ])
        );

        $deliverySales = (float)($row['hnr_delivery_sales'] ?? 0);
        $carryoutSales = (float)($row['hnr_carryout_sales'] ?? 0);

        return round($deliverySales + $carryoutSales, 2);
    }

    public function getHnrQuantity(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, [
                'hnr_delivery_quantity',
                'hnr_carryout_quantity'
            ])
        );

        $deliveryQty = (int)($row['hnr_delivery_quantity'] ?? 0);
        $carryoutQty = (int)($row['hnr_carryout_quantity'] ?? 0);

        return $deliveryQty + $carryoutQty;
    }

    public function getBreadSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, [
                'bread_delivery_sales',
                'bread_carryout_sales'
            ])
        );

        $deliverySales = (float)($row['bread_delivery_sales'] ?? 0);
        $carryoutSales = (float)($row['bread_carryout_sales'] ?? 0);

        return round($deliverySales + $carryoutSales, 2);
    }

    public function getBreadQuantity(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, [
                'bread_delivery_quantity',
                'bread_carryout_quantity'
            ])
        );

        $deliveryQty = (int)($row['bread_delivery_quantity'] ?? 0);
        $carryoutQty = (int)($row['bread_carryout_quantity'] ?? 0);

        return $deliveryQty + $carryoutQty;
    }

    public function getWingsSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, [
                'wings_delivery_sales',
                'wings_carryout_sales'
            ])
        );

        $deliverySales = (float)($row['wings_delivery_sales'] ?? 0);
        $carryoutSales = (float)($row['wings_carryout_sales'] ?? 0);

        return round($deliverySales + $carryoutSales, 2);
    }

    public function getWingsQuantity(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, [
                'wings_delivery_quantity',
                'wings_carryout_quantity'
            ])
        );

        $deliveryQty = (int)($row['wings_delivery_quantity'] ?? 0);
        $carryoutQty = (int)($row['wings_carryout_quantity'] ?? 0);

        return $deliveryQty + $carryoutQty;
    }

    public function getBeveragesSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, [
                'beverages_delivery_sales',
                'beverages_carryout_sales'
            ])
        );

        $deliverySales = (float)($row['beverages_delivery_sales'] ?? 0);
        $carryoutSales = (float)($row['beverages_carryout_sales'] ?? 0);

        return round($deliverySales + $carryoutSales, 2);
    }

    public function getBeveragesQuantity(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, [
                'beverages_delivery_quantity',
                'beverages_carryout_quantity'
            ])
        );

        $deliveryQty = (int)($row['beverages_delivery_quantity'] ?? 0);
        $carryoutQty = (int)($row['beverages_carryout_quantity'] ?? 0);

        return $deliveryQty + $carryoutQty;
    }

    public function getOtherFoodsSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, [
                'other_foods_delivery_sales',
                'other_foods_carryout_sales'
            ])
        );

        $deliverySales = (float)($row['other_foods_delivery_sales'] ?? 0);
        $carryoutSales = (float)($row['other_foods_carryout_sales'] ?? 0);

        return round($deliverySales + $carryoutSales, 2);
    }

    public function getOtherFoodsQuantity(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, [
                'other_foods_delivery_quantity',
                'other_foods_carryout_quantity'
            ])
        );

        $deliveryQty = (int)($row['other_foods_delivery_quantity'] ?? 0);
        $carryoutQty = (int)($row['other_foods_carryout_quantity'] ?? 0);

        return $deliveryQty + $carryoutQty;
    }


    public function getProductCategorySales(string $store, Carbon $startDate, Carbon $endDate): array
    {
        return [
            'franchise_store'        => $store,
            'start_date'             => $startDate->toDateString(),
            'end_date'               => $endDate->toDateString(),
            'pizza_quantity'         => $this->getPizzaQuantity($store, $startDate, $endDate),
            'pizza_sales'            => $this->getPizzaSales($store, $startDate, $endDate),
            'hnr_quantity'           => $this->getHnrQuantity($store, $startDate, $endDate),
            'hnr_sales'              => $this->getHnrSales($store, $startDate, $endDate),
            'bread_quantity'         => $this->getBreadQuantity($store, $startDate, $endDate),
            'bread_sales'            => $this->getBreadSales($store, $startDate, $endDate),
            'wings_quantity'         => $this->getWingsQuantity($store, $startDate, $endDate),
            'wings_sales'            => $this->getWingsSales($store, $startDate, $endDate),
            'beverages_quantity'     => $this->getBeveragesQuantity($store, $startDate, $endDate),
            'beverages_sales'        => $this->getBeveragesSales($store, $startDate, $endDate),
            'other_foods_quantity'   => $this->getOtherFoodsQuantity($store, $startDate, $endDate),
            'other_foods_sales'      => $this->getOtherFoodsSales($store, $startDate, $endDate),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FINANCIAL METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getSalesTax(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['sales_tax'])
        );

        return round((float)($row['sales_tax'] ?? 0), 2);
    }

    public function getDeliveryFees(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['delivery_fees'])
        );

        return round((float)($row['delivery_fees'] ?? 0), 2);
    }

    public function getDeliveryTips(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['delivery_tips'])
        );

        return round((float)($row['delivery_tips'] ?? 0), 2);
    }

    public function getStoreTips(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['store_tips'])
        );

        return round((float)($row['store_tips'] ?? 0), 2);
    }

    public function getTotalTips(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['total_tips'])
        );

        return round((float)($row['total_tips'] ?? 0), 2);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PAYMENT METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getCashSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['cash_sales'])
        );

        return round((float)($row['cash_sales'] ?? 0), 2);
    }

    public function getCreditCardSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['credit_card_sales'])
        );

        return round((float)($row['credit_card_sales'] ?? 0), 2);
    }

    public function getPrepaidSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['prepaid_sales'])
        );

        return round((float)($row['prepaid_sales'] ?? 0), 2);
    }

    public function getOverShort(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['over_short'])
        );

        return round((float)($row['over_short'] ?? 0), 2);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PORTAL METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getPortalEligibleOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['portal_eligible_orders'])
        );

        return (int)($row['portal_eligible_orders'] ?? 0);
    }

    public function getPortalUsedOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['portal_used_orders'])
        );

        return (int)($row['portal_used_orders'] ?? 0);
    }

    public function getPortalUsageRate(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $eligible = $this->getPortalEligibleOrders($store, $startDate, $endDate);
        $used     = $this->getPortalUsedOrders($store, $startDate, $endDate);

        return $eligible > 0 ? round(($used / $eligible) * 100, 2) : 0.0;
    }

    public function getPortalOnTimeOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['portal_on_time_orders'])
        );

        return (int)($row['portal_on_time_orders'] ?? 0);
    }

    public function getPortalOnTimeRate(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $used   = $this->getPortalUsedOrders($store, $startDate, $endDate);
        $onTime = $this->getPortalOnTimeOrders($store, $startDate, $endDate);

        return $used > 0 ? round(($onTime / $used) * 100, 2) : 0.0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // WASTE METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getTotalWasteItems(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['total_waste_items'])
        );

        return (int)($row['total_waste_items'] ?? 0);
    }

    public function getTotalWasteCost(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['total_waste_cost'])
        );

        return round((float)($row['total_waste_cost'] ?? 0), 2);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DIGITAL METRICS
    // ═════════════════════════════════════════════════════════════════════════

    public function getDigitalOrders(string $store, Carbon $startDate, Carbon $endDate): int
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['digital_orders'])
        );

        return (int)($row['digital_orders'] ?? 0);
    }

    public function getDigitalSales(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $row = $this->fetchSingle(
            $this->baseStoreRequest($store, $startDate, $endDate, ['digital_sales'])
        );

        return round((float)($row['digital_sales'] ?? 0), 2);
    }

    public function getDigitalPenetration(string $store, Carbon $startDate, Carbon $endDate): float
    {
        $totalSales   = $this->getSales($store, $startDate, $endDate);
        $digitalSales = $this->getDigitalSales($store, $startDate, $endDate);

        return $totalSales > 0 ? round(($digitalSales / $totalSales) * 100, 2) : 0.0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ITEM-LEVEL QUERIES
    // ═════════════════════════════════════════════════════════════════════════

    public function getItemSales(string $store, Carbon $startDate, Carbon $endDate, string $itemId): float
    {
        $row = $this->fetchSingle(
            $this->baseItemRequest(
                $store,
                $startDate,
                $endDate,
                ['gross_sales'],
                ['item_id' => $itemId]
            )
        );

        return round((float)($row['gross_sales'] ?? 0), 2);
    }

    public function getItemQuantity(string $store, Carbon $startDate, Carbon $endDate, string $itemId): int
    {
        $row = $this->fetchSingle(
            $this->baseItemRequest(
                $store,
                $startDate,
                $endDate,
                ['quantity_sold'],
                ['item_id' => $itemId]
            )
        );

        return (int)($row['quantity_sold'] ?? 0);
    }

    public function getItemsByName(string $store, Carbon $startDate, Carbon $endDate, string $itemName): array
    {
        $data = $this->fetchAll(
            $this->baseItemRequest(
                $store,
                $startDate,
                $endDate,
                ['quantity_sold', 'gross_sales', 'net_sales', 'avg_item_price'],
                ['menu_item_name' => $itemName]
            )
        );

        $quantity   = array_sum(array_column($data, 'quantity_sold'));
        $grossSales = array_sum(array_column($data, 'gross_sales'));
        $netSales   = array_sum(array_column($data, 'net_sales'));
        $avgPrice   = 0.0;

        if (count($data) > 0) {
            $avgValues = array_column($data, 'avg_item_price');
            $avgPrice  = round(array_sum($avgValues) / count($avgValues), 2);
        }

        return [
            'item_name'     => $itemName,
            'quantity_sold' => (int)$quantity,
            'gross_sales'   => round($grossSales, 2),
            'net_sales'     => round($netSales, 2),
            'avg_price'     => $avgPrice,
        ];
    }

    public function getBreadBoost(string $store, Carbon $startDate, Carbon $endDate): array
    {
        // Classic pizzas
        $classicRows = $this->fetchAll(
            $this->baseItemRequest(
                $store,
                $startDate,
                $endDate,
                ['quantity_sold'],
                ['menu_item_name' => ['Classic Pepperoni', 'Classic Cheese']]
            )
        );
        $classicPizzaQty = array_sum(array_column($classicRows, 'quantity_sold'));

        // Crazy Bread
        $breadRows = $this->fetchAll(
            $this->baseItemRequest(
                $store,
                $startDate,
                $endDate,
                ['quantity_sold'],
                ['menu_item_name' => 'Crazy Bread']
            )
        );
        $crazyBreadQty = array_sum(array_column($breadRows, 'quantity_sold'));

        // Other pizzas
        $excludedIds = [-1, 6, 7, 8, 9, 101001, 101002, 101288, 103044, 202901, 101289, 204100, 204200];

        $otherRows = $this->fetchAll(
            $this->baseItemRequest(
                $store,
                $startDate,
                $endDate,
                ['quantity_sold'],
                [
                    'menu_item_account' => 'Pizza',
                    'exclude_item_ids'  => $excludedIds,
                    'exclude_names'     => ['Classic Pepperoni', 'Classic Cheese'],
                ]
            )
        );
        $otherPizzaQty = array_sum(array_column($otherRows, 'quantity_sold'));

        // Estimated attachment
        $estimatedClassicWithBread = min($classicPizzaQty, $crazyBreadQty);
        $remainingBread            = max(0, $crazyBreadQty - $estimatedClassicWithBread);
        $estimatedOtherWithBread   = min($otherPizzaQty, $remainingBread);

        return [
            'franchise_store'       => $store,
            'start_date'            => $startDate->toDateString(),
            'end_date'              => $endDate->toDateString(),
            'classic_orders'        => (int)$classicPizzaQty,
            'classic_with_bread'    => (int)$estimatedClassicWithBread,
            'classic_attach_rate'   => $classicPizzaQty > 0
                ? round(($estimatedClassicWithBread / $classicPizzaQty) * 100, 2)
                : 0,
            'other_pizza_orders'    => (int)$otherPizzaQty,
            'other_with_bread'      => (int)$estimatedOtherWithBread,
            'other_attach_rate'     => $otherPizzaQty > 0
                ? round(($estimatedOtherWithBread / $otherPizzaQty) * 100, 2)
                : 0,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // COMPREHENSIVE SUMMARY METHODS
    // ═════════════════════════════════════════════════════════════════════════

    public function getChannelData(string $store, Carbon $startDate, Carbon $endDate): array
    {
        return [
            'franchise_store'   => $store,
            'start_date'        => $startDate->toDateString(),
            'end_date'          => $endDate->toDateString(),
            'phone_orders'      => $this->getPhoneOrders($store, $startDate, $endDate),
            'phone_sales'       => $this->getPhoneSales($store, $startDate, $endDate),
            'website_orders'    => $this->getWebsiteOrders($store, $startDate, $endDate),
            'website_sales'     => $this->getWebsiteSales($store, $startDate, $endDate),
            'mobile_orders'     => $this->getMobileOrders($store, $startDate, $endDate),
            'mobile_sales'      => $this->getMobileSales($store, $startDate, $endDate),
            'call_center_orders' => $this->getCallCenterOrders($store, $startDate, $endDate),
            'call_center_sales' => $this->getCallCenterSales($store, $startDate, $endDate),
            'drive_thru_orders' => $this->getDriveThruOrders($store, $startDate, $endDate),
            'drive_thru_sales'  => $this->getDriveThruSales($store, $startDate, $endDate),
            'doordash_orders'   => $this->getDoordashOrders($store, $startDate, $endDate),
            'doordash_sales'    => $this->getDoordashSales($store, $startDate, $endDate),
            'ubereats_orders'   => $this->getUbereatsOrders($store, $startDate, $endDate),
            'ubereats_sales'    => $this->getUbereatsSales($store, $startDate, $endDate),
            'grubhub_orders'    => $this->getGrubhubOrders($store, $startDate, $endDate),
            'grubhub_sales'     => $this->getGrubhubSales($store, $startDate, $endDate),
            'delivery_orders'   => $this->getDeliveryOrders($store, $startDate, $endDate),
            'delivery_sales'    => $this->getDeliverySales($store, $startDate, $endDate),
            'carryout_orders'   => $this->getCarryoutOrders($store, $startDate, $endDate),
            'carryout_sales'    => $this->getCarryoutSales($store, $startDate, $endDate),
        ];
    }

    public function getFinalSummaries(string $store, Carbon $startDate, Carbon $endDate): array
    {
        $totalSales   = $this->getSales($store, $startDate, $endDate);
        $digitalSales = $this->getDigitalSales($store, $startDate, $endDate);

        return [
            'franchise_store'         => $store,
            'start_date'              => $startDate->toDateString(),
            'end_date'                => $endDate->toDateString(),
            'total_sales'             => $totalSales,
            'gross_sales'             => $this->getGrossSales($store, $startDate, $endDate),
            'net_sales'               => $this->getNetSales($store, $startDate, $endDate),
            'total_orders'            => $this->getOrders($store, $startDate, $endDate),
            'modified_orders'         => $this->getModifiedOrders($store, $startDate, $endDate),
            'refunded_orders'         => $this->getRefundedOrders($store, $startDate, $endDate),
            'customer_count'          => $this->getCustomerCount($store, $startDate, $endDate),
            'phone_sales'             => $this->getPhoneSales($store, $startDate, $endDate),
            'call_center_sales'       => $this->getCallCenterSales($store, $startDate, $endDate),
            'drive_thru_sales'        => $this->getDriveThruSales($store, $startDate, $endDate),
            'website_sales'           => $this->getWebsiteSales($store, $startDate, $endDate),
            'mobile_sales'            => $this->getMobileSales($store, $startDate, $endDate),
            'doordash_sales'          => $this->getDoordashSales($store, $startDate, $endDate),
            'grubhub_sales'           => $this->getGrubhubSales($store, $startDate, $endDate),
            'ubereats_sales'          => $this->getUbereatsSales($store, $startDate, $endDate),
            'delivery_sales'          => $this->getDeliverySales($store, $startDate, $endDate),
            'carryout_sales'          => $this->getCarryoutSales($store, $startDate, $endDate),
            'digital_sales'           => $digitalSales,
            'digital_sales_percent'   => $totalSales > 0 ? round(($digitalSales / $totalSales) * 100, 2) : 0,
            'portal_transactions'     => $this->getPortalEligibleOrders($store, $startDate, $endDate),
            'put_into_portal'         => $this->getPortalUsedOrders($store, $startDate, $endDate),
            'portal_used_percent'     => $this->getPortalUsageRate($store, $startDate, $endDate),
            'put_in_portal_on_time'   => $this->getPortalOnTimeOrders($store, $startDate, $endDate),
            'in_portal_on_time_percent' => $this->getPortalOnTimeRate($store, $startDate, $endDate),
            'delivery_tips'           => $this->getDeliveryTips($store, $startDate, $endDate),
            'store_tips'              => $this->getStoreTips($store, $startDate, $endDate),
            'total_tips'              => $this->getTotalTips($store, $startDate, $endDate),
            'over_short'              => $this->getOverShort($store, $startDate, $endDate),
            'cash_sales'              => $this->getCashSales($store, $startDate, $endDate),
            'total_waste_cost'        => $this->getTotalWasteCost($store, $startDate, $endDate),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // TABLE INFO (OPTIONAL)
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Kept for compatibility but now only calculates days difference.
     */
    public function getTableInfo(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'date_range_days' => $startDate->diffInDays($endDate),
            'store_summary_table' => 'INTELLIGENT_MIX',
            'item_summary_table'  => 'INTELLIGENT_MIX',
        ];
    }
}
