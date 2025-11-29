<?php

namespace App\Services\Import\Processors;

class AltaInventoryIngredientOrdersProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'alta_inventory_ingredient_orders';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'supplier', 'ingredient_id', 'invoice_number'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store', 'business_date', 'supplier', 'invoice_number',
            'purchase_order_number', 'ingredient_id', 'ingredient_description',
            'ingredient_category', 'ingredient_unit', 'unit_price', 'order_qty',
            'sent_qty', 'received_qty', 'total_cost',
        ];
    }
}
