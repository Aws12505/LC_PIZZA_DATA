<?php

namespace App\Models\Aggregation;

use App\Models\Analytics\AnalyticsModel;

class DailyItemSummary extends AnalyticsModel
{
    protected $table = 'daily_item_summary';

    protected $fillable = [
        'franchise_store', 'business_date', 'item_id', 'menu_item_name',
        'menu_item_account', 'quantity_sold', 'gross_sales', 'net_sales',
        'avg_item_price', 'delivery_quantity', 'carryout_quantity',
        'modified_quantity', 'refunded_quantity',
    ];

    protected $casts = [
        'business_date' => 'date',
        'quantity_sold' => 'integer',
        'gross_sales' => 'decimal:2',
        'net_sales' => 'decimal:2',
        'avg_item_price' => 'decimal:2',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }

    public function scopeForItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }
}
