<?php

namespace App\Services\Import\Processors;

class SummaryTransactionsProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'summarytransactions';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchisestore', 'businessdate', 'paymentmethod', 'subpaymentmethod'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'paymentmethod',
            'subpaymentmethod',
            'totalamount',
            'safqty',
            'saftotal',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['paymentmethod']);
    }
}
