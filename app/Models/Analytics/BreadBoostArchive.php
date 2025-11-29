<?php

namespace App\Models\Analytics;

class BreadBoostArchive extends AnalyticsModel
{
    protected $table = 'bread_boost_archive';

    protected $fillable = [
        'business_date',
        'franchise_store',
        'classic_order',
        'classic_with_bread',
        'other_pizza_order',
        'other_pizza_with_bread',
    ];

    protected $casts = [
        'business_date' => 'date',
        'classic_order' => 'integer',
        'classic_with_bread' => 'integer',
        'other_pizza_order' => 'integer',
        'other_pizza_with_bread' => 'integer',
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
