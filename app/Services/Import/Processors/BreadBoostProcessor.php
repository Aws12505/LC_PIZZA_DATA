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

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'classicorder' => 'classic_order',
            'classicwithbread' => 'classic_with_bread',
            'otherpizzaorder' => 'other_pizza_order',
            'otherpizzawithbread' => 'other_pizza_with_bread',
        ]);
    }
}
