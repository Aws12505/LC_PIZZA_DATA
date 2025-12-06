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
        // FIXED: Added transactiontype back (was missing in new code)
        return ['franchise_store', 'business_date', 'order_id', 'transaction_type'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'business_date',
            'order_id',
            'date_time_placed',
            'date_time_fulfilled',
            'royalty_obligation',
            'quantity',
            'customer_count',
            'taxable_amount',
            'non_taxable_amount',
            'tax_exempt_amount',
            'non_royalty_amount',
            'sales_tax',
            'gross_sales',
            'occupational_tax',
            'employee',
            'override_approval_employee',
            'order_placed_method',
            'order_fulfilled_method',
            'delivery_tip',
            'delivery_tip_tax',
            'delivery_fee',
            'delivery_fee_tax',
            'delivery_service_fee',
            'delivery_service_fee_tax',
            'delivery_small_order_fee',
            'delivery_small_order_fee_tax',
            'modified_order_amount',
            'modification_reason',
            'refunded',
            'payment_methods',
            'transaction_type',
            'store_tip_amount',
            'promise_date',
            'tax_exemption_id',
            'tax_exemption_entity_name',
            'user_id',
            'hnrOrder',
            'broken_promise',
            'portal_eligible',
            'portal_used',
            'put_into_portal_before_promise_time',
            'portal_compartments_used',
            'time_loaded_into_portal',
        ];
    }

    protected function transformData(array $row): array
    {
        // Parse datetime fields
        $row['datetimeplaced'] = $this->parseDateTime($row['datetimeplaced'] ?? null);
        $row['datetimefulfilled'] = $this->parseDateTime($row['datetimefulfilled'] ?? null);
        $row['promisedate'] = $this->parseDateTime($row['promisedate'] ?? null);
        $row['timeloadedintoportal'] = $this->parseDateTime($row['timeloadedintoportal'] ?? null);

        // Parse numeric fields
        foreach (['royaltyobligation', 'quantity', 'customercount', 'grosssales'] as $field) {
            $row[$field] = $this->toNumeric($row[$field] ?? null);
        }

        return $row;
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchise_store']) 
            && !empty($row['business_date']) 
            && !empty($row['orderid']);
    }
}
