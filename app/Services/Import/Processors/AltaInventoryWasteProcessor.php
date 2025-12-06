<?php

namespace App\Services\Import\Processors;

class AltaInventoryWasteProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'alta_inventory_waste';
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
            'itemdescription',
            'wastereason',
            'unitfoodcost',
            'qty',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['itemid']);
    }
}
