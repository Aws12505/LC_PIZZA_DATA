<?php

namespace App\Services\Import\Processors;

class FinancialViewsProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'financialviews';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchisestore', 'businessdate', 'subaccount', 'area'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'area',
            'subaccount',
            'amount',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['subaccount']) 
            && !empty($row['area']);
    }
}
