<?php

namespace App\Services\Import\Processors;

class SummaryItemsProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'summary_items';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'menu_item_name', 'item_id'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'business_date',
            'menu_item_name',
            'menu_item_account',
            'item_id',
            'item_quantity',
            'royalty_obligation',
            'taxable_amount',
            'non_taxable_amount',
            'tax_exempt_amount',
            'non_royalty_amount',
            'tax_included_amount',
        ];
    }

    protected function validate(array $row): bool
    {
        return true;
    }
}
