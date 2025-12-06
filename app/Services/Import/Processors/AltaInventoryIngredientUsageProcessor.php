<?php

namespace App\Services\Import\Processors;

class AltaInventoryIngredientUsageProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'altainventoryingredientusage';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchisestore', 'businessdate', 'countperiod', 'ingredientid'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'countperiod',
            'ingredientid',
            'ingredientdescription',
            'ingredientcategory',
            'ingredientunit',
            'ingredientunitcost',
            'startinginventoryqty',
            'receivedqty',
            'nettransferredqty',
            'endinginventoryqty',
            'actualusage',
            'theoreticalusage',
            'varianceqty',
            'wasteqty',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['countperiod']) 
            && !empty($row['ingredientid']);
    }
}
