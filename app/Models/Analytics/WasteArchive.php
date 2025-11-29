<?php

namespace App\Models\Analytics;

class WasteArchive extends AnalyticsModel
{
    protected $table = 'waste_archive';

    protected $fillable = [
        'business_date', 'franchise_store', 'cv_item_id', 'menu_item_name',
        'expired', 'waste_date_time', 'produce_date_time', 'waste_reason',
        'cv_order_id', 'waste_type', 'item_cost', 'quantity',
    ];

    protected $casts = [
        'business_date' => 'date',
        'expired' => 'boolean',
        'waste_date_time' => 'datetime',
        'produce_date_time' => 'datetime',
        'item_cost' => 'decimal:4',
        'quantity' => 'integer',
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
