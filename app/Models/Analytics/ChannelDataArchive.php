<?php

namespace App\Models\Analytics;

class ChannelDataArchive extends AnalyticsModel
{
    protected $table = 'channel_data_archive';

    protected $fillable = [
        'franchise_store',
        'business_date',
        'category',
        'sub_category',
        'order_placed_method',
        'order_fulfilled_method',
        'amount',
    ];

    protected $casts = [
        'business_date' => 'date',
        'amount' => 'decimal:2',
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
