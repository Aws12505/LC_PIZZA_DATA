<?php

namespace App\Models\Aggregation;



class YearlyItemSummary extends AggregationModel
{
    protected $table = 'yearly_item_summary';

    protected $fillable = [
        'franchise_store', 'year_num',
        'item_id', 'menu_item_name', 'menu_item_account',
        'quantity_sold', 'gross_sales', 'net_sales', 'avg_item_price', 'avg_daily_quantity',
        'delivery_quantity', 'carryout_quantity',
    ];

    protected $casts = [
        'year_num' => 'integer',
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
