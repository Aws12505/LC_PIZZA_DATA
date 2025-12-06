<?php

namespace App\Services\Import\Processors;

class AltaInventoryIngredientUsageProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'alta_inventory_ingredient_usage';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'count_period', 'ingredient_id'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'business_date',
            'count_period',
            'ingredient_id',
            'ingredient_description',
            'ingredient_category',
            'ingredient_unit',
            'ingredient_unit_cost',
            'starting_inventory_qty',
            'received_qty',
            'net_transferred_qty',
            'ending_inventory_qty',
            'actual_usage',
            'theoretical_usage',
            'variance_qty',
            'waste_qty',
        ];
    }

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'countperiod' => 'count_period',
            'ingredientid' => 'ingredient_id',
            'ingredientdescription' => 'ingredient_description',
            'ingredientcategory' => 'ingredient_category',
            'ingredientunit' => 'ingredient_unit',
            'ingredientunitcost' => 'ingredient_unit_cost',
            'startinginventoryqty' => 'starting_inventory_qty',
            'receivedqty' => 'received_qty',
            'nettransferredqty' => 'net_transferred_qty',
            'endinginventoryqty' => 'ending_inventory_qty',
            'actualusage' => 'actual_usage',
            'theoreticalusage' => 'theoretical_usage',
            'varianceqty' => 'variance_qty',
            'wasteqty' => 'waste_qty',
        ]);
    }
}
