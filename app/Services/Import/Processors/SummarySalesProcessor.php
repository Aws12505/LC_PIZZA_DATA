<?php

namespace App\Services\Import\Processors;

class SummarySalesProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'summary_sales';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'business_date',
            'royalty_obligation',
            'customer_count',
            'taxable_amount',
            'non_taxable_amount',
            'tax_exempt_amount',
            'non_royalty_amount',
            'refund_amount',
            'sales_tax',
            'gross_sales',
            'occupational_tax',
            'delivery_tip',
            'delivery_fee',
            'delivery_service_fee',
            'delivery_small_order_fee',
            'modified_order_amount',
            'store_tip_amount',
            'prepaid_cash_orders',
            'prepaid_non_cash_orders',
            'prepaid_sales',
            'prepaid_delivery_tip',
            'prepaid_in_store_tip_amount',
            'over_short',
            'previous_day_refunds',
            'saf',
            'manager_notes',
        ];
    }

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'royaltyobligation' => 'royalty_obligation',
            'customercount' => 'customer_count',
            'taxableamount' => 'taxable_amount',
            'nontaxableamount' => 'non_taxable_amount',
            'taxexemptamount' => 'tax_exempt_amount',
            'nonroyaltyamount' => 'non_royalty_amount',
            'refundamount' => 'refund_amount',
            'salestax' => 'sales_tax',
            'grosssales' => 'gross_sales',
            'occupationaltax' => 'occupational_tax',
            'deliverytip' => 'delivery_tip',
            'deliveryfee' => 'delivery_fee',
            'deliveryservicefee' => 'delivery_service_fee',
            'deliverysmallorderfee' => 'delivery_small_order_fee',
            'modifiedorderamount' => 'modified_order_amount',
            'storetipamount' => 'store_tip_amount',
            'prepaidcashorders' => 'prepaid_cash_orders',
            'prepaidnoncashorders' => 'prepaid_non_cash_orders',
            'prepaidsales' => 'prepaid_sales',
            'prepaiddeliverytip' => 'prepaid_delivery_tip',
            'prepaidinstoretipamount' => 'prepaid_in_store_tip_amount',
            'overshort' => 'over_short',
            'previousdayrefunds' => 'previous_day_refunds',
            'saf' => 'saf',
            'managernotes' => 'manager_notes',
        ]);
    }

    protected function transformData(array $row): array
    {
        $numericFields = [
            'royalty_obligation', 'customer_count', 'taxable_amount', 'non_taxable_amount',
            'tax_exempt_amount', 'non_royalty_amount', 'refund_amount', 'sales_tax',
            'gross_sales', 'occupational_tax', 'delivery_tip', 'delivery_fee',
            'delivery_service_fee', 'delivery_small_order_fee', 'modified_order_amount',
            'store_tip_amount', 'prepaid_cash_orders', 'prepaid_non_cash_orders',
            'prepaid_sales', 'prepaid_delivery_tip', 'prepaid_in_store_tip_amount',
            'over_short', 'previous_day_refunds', 'saf'
        ];

        foreach ($numericFields as $field) {
            if (isset($row[$field])) {
                $row[$field] = $this->toNumeric($row[$field]);
            }
        }

        return $row;
    }
}
