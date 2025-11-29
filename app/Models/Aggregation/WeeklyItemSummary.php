<?php

namespace App\Models\Aggregation;

use App\Models\Analytics\AnalyticsModel;
class WeeklyItemSummary extends AnalyticsModel
{
    protected $table = 'weekly_item_summary';

    protected $fillable = [
        'franchise_store', 'year_num', 'week_num', 'week_start_date', 'week_end_date',
        'item_id', 'menu_item_name', 'menu_item_account', 'quantity_sold',
        'gross_sales', 'net_sales', 'avg_item_price', 'avg_daily_quantity',
        'delivery_quantity', 'carryout_quantity',
    ];

    protected $casts = [
        'year_num' => 'integer',
        'week_num' => 'integer',
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'quantity_sold' => 'integer',
        'gross_sales' => 'decimal:2',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }
}
