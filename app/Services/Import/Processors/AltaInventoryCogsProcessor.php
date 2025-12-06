<?php

namespace App\Services\Import\Processors;

class AltaInventoryCogsProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'alta_inventory_cogs';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchisestore', 'businessdate', 'countperiod', 'inventorycategory'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'countperiod',
            'inventorycategory',
            'startingvalue',
            'receivedvalue',
            'nettransfervalue',
            'endingvalue',
            'usedvalue',
            'theoreticalusagevalue',
            'variancevalue',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['countperiod']) 
            && !empty($row['inventorycategory']);
    }
}
