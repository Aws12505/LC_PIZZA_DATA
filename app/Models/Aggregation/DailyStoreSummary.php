<?php

namespace App\Models\Aggregation;

use App\Models\Analytics\AnalyticsModel;

class DailyStoreSummary extends AnalyticsModel
{
    protected $table = 'daily_store_summary';

    protected $fillable = [
        'franchise_store', 'business_date', 'total_sales', 'gross_sales', 'net_sales',
        'refund_amount', 'total_orders', 'completed_orders', 'cancelled_orders',
        'modified_orders', 'refunded_orders', 'avg_order_value', 'customer_count',
        'avg_customers_per_order', 'phone_orders', 'phone_sales', 'website_orders',
        'website_sales', 'mobile_orders', 'mobile_sales', 'call_center_orders',
        'call_center_sales', 'drive_thru_orders', 'drive_thru_sales', 'doordash_orders',
        'doordash_sales', 'ubereats_orders', 'ubereats_sales', 'grubhub_orders',
        'grubhub_sales', 'delivery_orders', 'delivery_sales', 'carryout_orders',
        'carryout_sales', 'pizza_quantity', 'pizza_sales', 'hnr_quantity', 'hnr_sales',
        'bread_quantity', 'bread_sales', 'wings_quantity', 'wings_sales',
        'beverages_quantity', 'beverages_sales', 'sales_tax', 'delivery_fees',
        'delivery_tips', 'store_tips', 'total_tips', 'cash_sales', 'credit_card_sales',
        'prepaid_sales', 'over_short', 'portal_eligible_orders', 'portal_used_orders',
        'portal_usage_rate', 'portal_on_time_orders', 'portal_on_time_rate',
        'total_waste_items', 'total_waste_cost', 'digital_orders', 'digital_sales',
        'digital_penetration',
    ];

    protected $casts = [
        'business_date' => 'date',
        'total_sales' => 'decimal:2',
        'total_orders' => 'integer',
        'customer_count' => 'integer',
        'avg_order_value' => 'decimal:2',
        'portal_usage_rate' => 'decimal:2',
        'digital_penetration' => 'decimal:2',
    ];

    /**
     * Scope to filter by store
     */
    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('business_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by month
     */
    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('business_date', $year)
                    ->whereMonth('business_date', $month);
    }
}
