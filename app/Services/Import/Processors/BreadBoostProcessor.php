<?php

namespace App\Services\Import\Processors;

class BreadBoostProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'bread_boost';
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
            'classic_order',
            'classic_with_bread',
            'other_pizza_order',
            'other_pizza_with_bread',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchise_store']) && !empty($row['business_date']);
    }
}
