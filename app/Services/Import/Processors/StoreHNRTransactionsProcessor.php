<?php

namespace App\Services\Import\Processors;

class StoreHNRTransactionsProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'store_HNR_transactions';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'item_id'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'business_date',
            'item_id',
            'item_name',
            'transactions',
            'promise_met_transactions',
            'promise_met_percentage',
            'transactions_with_CC',
            'promise_met_transactions_cc',
            'promise_met_percentage_cc',
        ];
    }

    protected function validate(array $row): bool
    {
        return true;
    }
}
