<?php

namespace App\Models\Aggregation;

use App\Models\Analytics\AnalyticsModel;

class YearlyStoreSummary extends AnalyticsModel
{
    protected $table = 'yearly_store_summary';

    protected $fillable = [
        'franchise_store', 'year_num', 'total_sales', 'gross_sales', 'net_sales',
        'total_orders', 'customer_count', 'operational_days', 'operational_months',
        'avg_daily_sales', 'avg_monthly_sales', 'sales_vs_prior_year',
        'sales_growth_percent', 'phone_orders', 'phone_sales', 'website_orders',
        'website_sales', 'delivery_orders', 'delivery_sales', 'pizza_quantity',
        'pizza_sales', 'bread_quantity', 'bread_sales', 'wings_quantity', 'wings_sales',
    ];

    protected $casts = [
        'year_num' => 'integer',
        'total_sales' => 'decimal:2',
        'total_orders' => 'integer',
        'operational_days' => 'integer',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }
}
