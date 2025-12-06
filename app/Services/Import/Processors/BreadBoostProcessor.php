<?php

namespace App\Services\Import\Processors;

class BreadBoostProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'breadboost';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchisestore', 'businessdate'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'businessdate',
            'franchisestore',
            'classicorder',
            'classicwithbread',
            'otherpizzaorder',
            'otherpizzawithbread',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) && !empty($row['businessdate']);
    }
}
