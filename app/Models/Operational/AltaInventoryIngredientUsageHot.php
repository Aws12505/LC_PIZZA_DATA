<?php

namespace App\Models\Operational;

class AltaInventoryIngredientUsageHot extends OperationalModel
{
    protected $table = 'alta_inventory_ingredient_usage_hot';

    protected $fillable = [
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

    protected $casts = [
        'business_date' => 'date',
        'ingredient_unit_cost' => 'decimal:2',
        'starting_inventory_qty' => 'decimal:2',
        'received_qty' => 'decimal:2',
        'actual_usage' => 'decimal:2',
        'variance_qty' => 'decimal:2',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }
}
