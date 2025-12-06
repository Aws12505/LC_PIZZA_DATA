<?php

namespace App\Services\Import\Processors;

class AltaInventoryIngredientOrdersProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'alta_inventory_ingredient_orders';
    }

    protected function getUniqueKeys(): array
    {
        // FIXED: Added purchaseordernumber back (was missing in new code)
        return [
            'franchisestore', 
            'businessdate', 
            'supplier', 
            'invoicenumber', 
            'purchaseordernumber', 
            'ingredientid'
        ];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchisestore',
            'businessdate',
            'supplier',
            'invoicenumber',
            'purchaseordernumber',
            'ingredientid',
            'ingredientdescription',
            'ingredientcategory',
            'ingredientunit',
            'unitprice',
            'orderqty',
            'sentqty',
            'receivedqty',
            'totalcost',
        ];
    }

    protected function validate(array $row): bool
    {
        return !empty($row['franchisestore']) 
            && !empty($row['businessdate']) 
            && !empty($row['supplier']) 
            && !empty($row['invoicenumber']) 
            && !empty($row['ingredientid']);
    }
}
