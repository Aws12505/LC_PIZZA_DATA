<?php

namespace App\Models\Aggregation;


class MonthlyItemSummary extends AggregationModel
{
    protected $table = 'monthly_item_summary';

    protected $fillable = [
        'franchise_store', 'year_num', 'month_num', 'item_id', 'menu_item_name',
        'menu_item_account', 'quantity_sold', 'gross_sales', 'net_sales',
        'avg_item_price', 'avg_daily_quantity', 'quantity_vs_prior_month',
        'quantity_growth_percent', 'sales_vs_prior_month', 'sales_growth_percent',
        'delivery_quantity', 'carryout_quantity',
    ];

    protected $casts = [
        'year_num' => 'integer',
        'month_num' => 'integer',
        'quantity_sold' => 'integer',
        'gross_sales' => 'decimal:2',
        'avg_item_price' => 'decimal:2',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }
}
