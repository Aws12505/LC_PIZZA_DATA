<?php

namespace App\Services\Import\Processors;

class HourlySalesProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'hourlysales';
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
            'totalsales',
            'phonesales',
            'callcentersales',
            'drivethrusales',
            'websitesales',
            'mobilesales',
            'websitesalesdelivery',
            'mobilesalesdelivery',
            'doordashsales',
            'ubereatssales',
            'grubhubsales',
            'ordercount',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['hour']);
    }
}
