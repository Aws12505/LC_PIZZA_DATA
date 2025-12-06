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
        return [
            'franchise_store',
            'business_date',
            'supplier',
            'invoice_number',
            'purchase_order_number',
            'ingredient_id'
        ];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'business_date',
            'supplier',
            'invoice_number',
            'purchase_order_number',
            'ingredient_id',
            'ingredient_description',
            'ingredient_category',
            'ingredient_unit',
            'unit_price',
            'order_qty',
            'sent_qty',
            'received_qty',
            'total_cost',
        ];
    }

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'supplier' => 'supplier',
            'invoicenumber' => 'invoice_number',
            'purchaseordernumber' => 'purchase_order_number',
            'ingredientid' => 'ingredient_id',
            'ingredientdescription' => 'ingredient_description',
            'ingredientcategory' => 'ingredient_category',
            'ingredientunit' => 'ingredient_unit',
            'unitprice' => 'unit_price',
            'orderqty' => 'order_qty',
            'sentqty' => 'sent_qty',
            'receivedqty' => 'received_qty',
            'totalcost' => 'total_cost',
        ]);
    }
}
