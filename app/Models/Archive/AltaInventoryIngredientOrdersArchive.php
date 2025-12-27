<?php

namespace App\Models\Archive;

class AltaInventoryIngredientOrdersArchive extends ArchiveModel
{
    protected $table = 'alta_inventory_ingredient_orders_archive';

    protected $fillable = [
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

    protected $casts = [
        'business_date' => 'date',
        'unit_price' => 'decimal:2',
        'order_qty' => 'decimal:2',
        'sent_qty' => 'decimal:2',
        'received_qty' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('business_date', [$startDate, $endDate]);
    }
}
