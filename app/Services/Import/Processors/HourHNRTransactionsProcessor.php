<?php

namespace App\Services\Import\Processors;

class HourHNRTransactionsProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'hour_HNR_transactions';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'hour'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'business_date',
            'hour',
            'transactions',
            'promise_broken_transactions',
            'promise_broken_percentage',
        ];
    }

    protected function validate(array $row): bool
    {
        return true;
    }
}
