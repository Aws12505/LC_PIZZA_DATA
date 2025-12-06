<?php

namespace App\Services\Import\Processors;

class FinalSummariesProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'finalsummaries';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchisestore', 'businessdate'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'totalsales',
            'modifiedorderqty',
            'refundedorderqty',
            'customercount',
            'phonesales',
            'callcentersales',
            'drivethrusales',
            'websitesales',
            'mobilesales',
            'doordashsales',
            'grubhubsales',
            'ubereatssales',
            'deliverysales',
            'digitalsalespercent',
            'portaltransactions',
            'putintoportal',
            'portalusedpercent',
            'putinportalontime',
            'inportalontimepercent',
            'deliverytips',
            'prepaiddeliverytips',
            'instoretipamount',
            'prepaidinstoretipamount',
            'totaltips',
            'overshort',
            'cashsales',
            'totalwastecost',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) && !empty($row['businessdate']);
    }
}
