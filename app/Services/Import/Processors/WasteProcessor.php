<?php

namespace App\Services\Import\Processors;

class WasteProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'waste';
    }

    protected function getUniqueKeys(): array
    {
        return ['businessdate', 'franchisestore', 'cvitemid', 'wastedatetime'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'businessdate',
            'franchisestore',
            'cvitemid',
            'menuitemname',
            'expired',
            'wastedatetime',
            'producedatetime',
            'wastereason',
            'cvorderid',
            'wastetype',
            'itemcost',
            'quantity',
        ];
    }

    protected function transformData(array $row): array
    {
        $row['wastedatetime'] = $this->parseDateTime($row['wastedatetime'] ?? null);
        $row['producedatetime'] = $this->parseDateTime($row['producedatetime'] ?? null);
        $row['expired'] = $this->toBoolean($row['expired'] ?? null);
        $row['itemcost'] = $this->toNumeric($row['itemcost'] ?? null);
        $row['quantity'] = $this->toNumeric($row['quantity'] ?? null);

        return $row;
    }

    protected function validate(array $row): bool
    {
        return !empty($row['businessdate']) 
            && !empty($row['franchisestore']) 
            && !empty($row['cvitemid'])
            && !empty($row['wastedatetime']);
    }
}
