<?php

namespace App\Services\Import\Processors;

class FinanceDataProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'financedata';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchisestore', 'businessdate'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'PizzaCarryout',
            'HNRCarryout',
            'BreadCarryout',
            'WingsCarryout',
            'BeveragesCarryout',
            'OtherFoodsCarryout',
            'SideItemsCarryout',
            'PizzaDelivery',
            'HNRDelivery',
            'BreadDelivery',
            'WingsDelivery',
            'BeveragesDelivery',
            'OtherFoodsDelivery',
            'SideItemsDelivery',
            'DeliveryCharges',
            'TOTALNetSales',
            'CustomerCount',
            'GiftCardNonRoyalty',
            'TotalNonRoyaltySales',
            'TotalNonDeliveryTips',
            'SalesTaxFoodBeverage',
            'SalesTaxDelivery',
            'TOTALSalesTaxQuantity',
            'DELIVERYQuantity',
            'DeliveryFee',
            'DeliveryServiceFee',
            'DeliverySmallOrderFee',
            'DeliveryLatetoPortalFee',
            'TOTALNativeAppDeliveryFees',
            'DeliveryTips',
            'DoorDashQuantity',
            'DoorDashOrderTotal',
            'GrubhubQuantity',
            'GrubhubOrderTotal',
            'UberEatsQuantity',
            'UberEatsOrderTotal',
            'ONLINEORDERINGMobileOrderQuantity',
            'ONLINEORDERINGOnlineOrderQuantity',
            'ONLINEORDERINGPayInStore',
            'AgentPrePaid',
            'AgentPayInStore',
            'AIPrePaid',
            'AIPayInStore',
            'PrePaidCashOrders',
            'PrePaidNonCashOrders',
            'PrePaidSales',
            'PrepaidDeliveryTips',
            'PrepaidInStoreTips',
            'MarketplacefromNonCashPaymentsbox',
            'AMEX',
            'TotalNonCashPayments',
            'creditcardCashPayments',
            'DebitCashPayments',
            'epayCashPayments',
            'NonCashPayments',
            'CashSales',
            'CashDropTotal',
            'OverShort',
            'Payouts',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) && !empty($row['businessdate']);
    }
}
