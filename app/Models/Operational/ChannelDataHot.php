<?php

namespace App\Models\Operational;

class ChannelDataHot extends OperationalModel
{
    protected $table = 'channel_data_hot';

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

    public function scopeForCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
