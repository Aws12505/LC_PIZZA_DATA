<?php

namespace App\Models\Aggregation;

use App\Models\Analytics\AnalyticsModel;

class WeeklyStoreSummary extends AnalyticsModel
{
    protected $table = 'weekly_store_summary';

    protected $fillable = [
        'franchise_store', 'year_num', 'week_num', 'week_start_date', 'week_end_date',
        'total_sales', 'gross_sales', 'net_sales', 'total_orders', 'customer_count',
        'avg_daily_sales', 'avg_daily_orders', 'sales_vs_prior_week',
        'sales_growth_percent', 'orders_vs_prior_week', 'orders_growth_percent',
        'phone_orders', 'phone_sales', 'website_orders', 'website_sales',
        'mobile_orders', 'mobile_sales', 'delivery_orders', 'delivery_sales',
        'pizza_quantity', 'pizza_sales', 'bread_quantity', 'bread_sales',
        'wings_quantity', 'wings_sales',
    ];

    protected $casts = [
        'year_num' => 'integer',
        'week_num' => 'integer',
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'total_sales' => 'decimal:2',
        'total_orders' => 'integer',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }
}
