<?php

namespace App\Services\Import\Processors;

class OrderLineProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'order_line';
    }

    /**
     * Order line uses REPLACE strategy - CSV is source of truth
     * We delete existing data for the date and insert fresh data
     */
    protected function getImportStrategy(): string
    {
        return self::STRATEGY_REPLACE;
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store', 'business_date', 'order_id', 'item_id',
            'date_time_placed', 'date_time_fulfilled', 'menu_item_name',
            'menu_item_account', 'bundle_name', 'net_amount', 'quantity',
            'royalty_item', 'taxable_item', 'tax_included_amount', 'employee',
            'override_approval_employee', 'order_placed_method', 'order_fulfilled_method',
            'modified_order_amount', 'modification_reason', 'payment_methods', 'refunded',
        ];
    }

    protected function transformData(array $row): array
    {
        // Parse datetime fields
        $row['date_time_placed'] = $this->parseDateTime($row['date_time_placed'] ?? null);
        $row['date_time_fulfilled'] = $this->parseDateTime($row['date_time_fulfilled'] ?? null);

        // Parse numeric fields
        $row['net_amount'] = $this->toNumeric($row['net_amount'] ?? null);
        $row['quantity'] = $this->toNumeric($row['quantity'] ?? null);
        $row['tax_included_amount'] = $this->toNumeric($row['tax_included_amount'] ?? null);
        $row['modified_order_amount'] = $this->toNumeric($row['modified_order_amount'] ?? null);

        return $row;
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchise_store']) && 
               !empty($row['business_date']) && 
               !empty($row['order_id']) &&
               !empty($row['item_id']);
    }
}
