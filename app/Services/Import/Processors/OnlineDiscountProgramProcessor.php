<?php

namespace App\Services\Import\Processors;

class OnlineDiscountProgramProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'onlinediscountprogram';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchisestore', 'businessdate', 'orderid'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'orderid',
            'businessdate',
            'paytype',
            'originalsubtotal',
            'modifiedsubtotal',
            'promocode',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['orderid']);
    }
}
