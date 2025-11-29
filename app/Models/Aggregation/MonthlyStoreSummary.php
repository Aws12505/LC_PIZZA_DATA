<?php

namespace App\Models\Aggregation;

use App\Models\Analytics\AnalyticsModel;
class MonthlyStoreSummary extends AnalyticsModel
{
    protected $table = 'monthly_store_summary';

    protected $fillable = [
        'franchise_store', 'year_num', 'month_num', 'month_name',
        'total_sales', 'gross_sales', 'net_sales', 'total_orders', 'customer_count',
        'operational_days', 'avg_daily_sales', 'avg_daily_orders',
        'sales_vs_prior_month', 'sales_growth_percent',
        'sales_vs_same_month_prior_year', 'yoy_growth_percent',
        'phone_orders', 'phone_sales', 'website_orders', 'website_sales',
        'mobile_orders', 'mobile_sales', 'delivery_orders', 'delivery_sales',
        'pizza_quantity', 'pizza_sales', 'bread_quantity', 'bread_sales',
        'wings_quantity', 'wings_sales',
    ];

    protected $casts = [
        'year_num' => 'integer',
        'month_num' => 'integer',
        'total_sales' => 'decimal:2',
        'total_orders' => 'integer',
        'operational_days' => 'integer',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }

    public function scopeForYear($query, $year)
    {
        return $query->where('year_num', $year);
    }
}
