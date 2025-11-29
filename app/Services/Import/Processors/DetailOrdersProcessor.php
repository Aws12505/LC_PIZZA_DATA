<?php

namespace App\Services\Import\Processors;

class DetailOrdersProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'detail_orders';
    }

    protected function getImportStrategy(): string
    {
        return self::STRATEGY_UPSERT;
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'order_id'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store', 'business_date', 'order_id', 'date_time_placed',
            'date_time_fulfilled', 'royalty_obligation', 'quantity', 'customer_count',
            'taxable_amount', 'non_taxable_amount', 'tax_exempt_amount', 'non_royalty_amount',
            'sales_tax', 'gross_sales', 'occupational_tax', 'employee',
            'override_approval_employee', 'order_placed_method', 'order_fulfilled_method',
            'delivery_tip', 'delivery_tip_tax', 'delivery_fee', 'delivery_fee_tax',
            'delivery_service_fee', 'delivery_service_fee_tax', 'delivery_small_order_fee',
            'delivery_small_order_fee_tax', 'modified_order_amount', 'modification_reason',
            'refunded', 'payment_methods', 'transaction_type', 'store_tip_amount',
            'promise_date', 'tax_exemption_id', 'tax_exemption_entity_name', 'user_id',
            'hnrOrder', 'broken_promise', 'portal_eligible', 'portal_used',
            'put_into_portal_before_promise_time', 'portal_compartments_used',
            'time_loaded_into_portal',
        ];
    }

    protected function transformData(array $row): array
    {
        // Parse datetime fields
        $row['date_time_placed'] = $this->parseDateTime($row['date_time_placed'] ?? null);
        $row['date_time_fulfilled'] = $this->parseDateTime($row['date_time_fulfilled'] ?? null);
        $row['promise_date'] = $this->parseDateTime($row['promise_date'] ?? null);
        $row['time_loaded_into_portal'] = $this->parseDateTime($row['time_loaded_into_portal'] ?? null);

        // Parse numeric fields
        foreach (['royalty_obligation', 'quantity', 'customer_count', 'gross_sales'] as $field) {
            $row[$field] = $this->toNumeric($row[$field] ?? null);
        }

        return $row;
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchise_store']) && 
               !empty($row['business_date']) && 
               !empty($row['order_id']);
    }
}
