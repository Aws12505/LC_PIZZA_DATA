<?php

namespace App\Services\Import\Processors;

class StoreHNRTransactionsProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'storeHNRtransactions';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchisestore', 'businessdate', 'itemid'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'itemid',
            'itemname',
            'transactions',
            'promisemettransactions',
            'promisemetpercentage',
            'transactionswithCC',
            'promisemettransactionscc',
            'promisemetpercentagecc',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['itemid']);
    }
}
