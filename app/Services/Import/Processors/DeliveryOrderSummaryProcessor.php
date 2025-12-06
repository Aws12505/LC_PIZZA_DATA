<?php

namespace App\Services\Import\Processors;

class DeliveryOrderSummaryProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'delivery_order_summary';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'business_date',
            'franchise_store',
            'orders_count',
            'product_cost',
            'tax',
            'occupational_tax',
            'delivery_charges',
            'delivery_charges_taxes',
            'service_charges',
            'service_charges_taxes',
            'small_order_charge',
            'small_order_charge_taxes',
            'delivery_late_charge',
            'tip',
            'tip_tax',
            'total_taxes',
            'order_total',
        ];
    }

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'orderscount' => 'orders_count',
            'productcost' => 'product_cost',
            'tax' => 'tax',
            'occupationaltax' => 'occupational_tax',
            'deliverycharges' => 'delivery_charges',
            'deliverychargestaxes' => 'delivery_charges_taxes',
            'servicecharges' => 'service_charges',
            'servicechargestaxes' => 'service_charges_taxes',
            'smallordercharge' => 'small_order_charge',
            'smallorderchargetaxes' => 'small_order_charge_taxes',
            'deliverylatecharge' => 'delivery_late_charge',
            'tip' => 'tip',
            'tiptax' => 'tip_tax',
            'totaltaxes' => 'total_taxes',
            'ordertotal' => 'order_total',
        ]);
    }
}
