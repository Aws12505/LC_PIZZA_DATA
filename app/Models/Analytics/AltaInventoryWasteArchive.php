<?php

namespace App\Models\Analytics;

class AltaInventoryWasteArchive extends AnalyticsModel
{
    protected $table = 'alta_inventory_waste_archive';

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

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('business_date', [$startDate, $endDate]);
    }
}
