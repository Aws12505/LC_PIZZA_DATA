<?php

namespace App\Services\Import\Processors;

class HourHNRTransactionsProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'hourHNRtransactions';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchisestore', 'businessdate', 'hour'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'hour',
            'transactions',
            'promisebrokentransactions',
            'promisebrokenpercentage',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['hour']);
    }
}
