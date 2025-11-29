<?php

namespace App\Services\Import\Processors;

class AltaInventoryWasteProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'alta_inventory_waste';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'item_id'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store', 'business_date', 'item_id', 'item_description',
            'waste_reason', 'unit_food_cost', 'qty',
        ];
    }
}
