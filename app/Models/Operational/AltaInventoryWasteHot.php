<?php

namespace App\Models\Operational;

class AltaInventoryWasteHot extends OperationalModel
{
    protected $table = 'alta_inventory_waste_hot';

    protected $fillable = [
        'franchise_store',
        'business_date',
        'item_id',
        'item_description',
        'waste_reason',
        'unit_food_cost',
        'qty',
    ];

    protected $casts = [
        'business_date' => 'date',
        'unit_food_cost' => 'decimal:2',
        'qty' => 'decimal:2',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }

    public function getTotalWasteCost(): float
    {
        return ($this->unit_food_cost ?? 0) * ($this->qty ?? 0);
    }
}
