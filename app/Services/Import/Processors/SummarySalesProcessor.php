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

    protected function transformData(array $row): array
    {
        // Parse all numeric fields
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

    protected function validate(array $row): bool
    {
        return true;
    }
}
