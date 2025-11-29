<?php

namespace App\Models\Aggregation;

use App\Models\Analytics\AnalyticsModel;
class QuarterlyStoreSummary extends AnalyticsModel
{
    protected $table = 'quarterly_store_summary';

    protected $fillable = [
        'franchise_store', 'year_num', 'quarter_num', 'quarter_start_date',
        'quarter_end_date', 'total_sales', 'gross_sales', 'net_sales',
        'total_orders', 'customer_count', 'operational_days', 'operational_months',
        'avg_daily_sales', 'avg_monthly_sales', 'sales_vs_prior_quarter',
        'sales_growth_percent', 'sales_vs_same_quarter_prior_year', 'yoy_growth_percent',
        'phone_orders', 'phone_sales', 'delivery_orders', 'delivery_sales',
        'pizza_quantity', 'pizza_sales', 'bread_quantity', 'bread_sales',
    ];

    protected $casts = [
        'year_num' => 'integer',
        'quarter_num' => 'integer',
        'quarter_start_date' => 'date',
        'quarter_end_date' => 'date',
        'total_sales' => 'decimal:2',
        'total_orders' => 'integer',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }
}
