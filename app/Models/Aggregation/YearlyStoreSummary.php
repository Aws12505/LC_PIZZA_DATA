<?php

namespace App\Models\Aggregation;

class YearlyStoreSummary extends AggregationModel
{
    protected $table = 'yearly_store_summary';

    protected $fillable = [
        'franchise_store',
        'year_num',

        // Sales
        'royalty_obligation',
        'gross_sales',
        'net_sales',
        'refund_amount',

        // Orders
        'total_orders',
        'completed_orders',
        'cancelled_orders',
        'modified_orders',
        'refunded_orders',
        'avg_order_value',
        'customer_count',

        // Operational / averages
        'operational_days',
        'operational_months',
        'avg_daily_sales',
        'avg_monthly_sales',

        // Growth
        'sales_vs_prior_year',
        'sales_growth_percent',

        // Channels
        'phone_orders',
        'phone_sales',
        'website_orders',
        'website_sales',
        'mobile_orders',
        'mobile_sales',
        'call_center_orders',
        'call_center_sales',
        'drive_thru_orders',
        'drive_thru_sales',

        // Marketplace
        'doordash_orders',
        'doordash_sales',
        'ubereats_orders',
        'ubereats_sales',
        'grubhub_orders',
        'grubhub_sales',

        // Fulfillment totals (SUM of category splits)
        'delivery_orders',
        'delivery_sales',
        'carryout_orders',
        'carryout_sales',

        // ✅ Category splits
        'pizza_delivery_quantity',
        'pizza_delivery_sales',
        'pizza_carryout_quantity',
        'pizza_carryout_sales',

        'hnr_delivery_quantity',
        'hnr_delivery_sales',
        'hnr_carryout_quantity',
        'hnr_carryout_sales',

        'bread_delivery_quantity',
        'bread_delivery_sales',
        'bread_carryout_quantity',
        'bread_carryout_sales',

        'wings_delivery_quantity',
        'wings_delivery_sales',
        'wings_carryout_quantity',
        'wings_carryout_sales',

        'beverages_delivery_quantity',
        'beverages_delivery_sales',
        'beverages_carryout_quantity',
        'beverages_carryout_sales',

        'other_foods_delivery_quantity',
        'other_foods_delivery_sales',
        'other_foods_carryout_quantity',
        'other_foods_carryout_sales',

        'side_items_delivery_quantity',
        'side_items_delivery_sales',
        'side_items_carryout_quantity',
        'side_items_carryout_sales',

        // Financial
        'sales_tax',
        'delivery_fees',
        'delivery_tips',
        'store_tips',
        'total_tips',

        // Payments
        'cash_sales',
        'over_short',

        // Portal
        'portal_eligible_orders',
        'portal_used_orders',
        'portal_usage_rate',
        'portal_on_time_orders',
        'portal_on_time_rate',

        // Digital
        'digital_orders',
        'digital_sales',
        'digital_penetration',

        'hnr_transactions',
        'hnr_broken_promises'
    ];

    protected $casts = [
        'year_num' => 'integer',

        'royalty_obligation' => 'decimal:2',
        'gross_sales' => 'decimal:2',
        'net_sales' => 'decimal:2',
        'refund_amount' => 'decimal:2',

        'total_orders' => 'integer',
        'customer_count' => 'integer',
        'avg_order_value' => 'decimal:2',

        'operational_days' => 'integer',
        'operational_months' => 'integer',
        'avg_daily_sales' => 'decimal:2',
        'avg_monthly_sales' => 'decimal:2',

        'sales_vs_prior_year' => 'decimal:2',
        'sales_growth_percent' => 'decimal:2',

        'delivery_orders' => 'integer',
        'delivery_sales' => 'decimal:2',
        'carryout_orders' => 'integer',
        'carryout_sales' => 'decimal:2',

        'cash_sales' => 'decimal:2',
        'over_short' => 'decimal:2',

        'portal_usage_rate' => 'decimal:2',
        'portal_on_time_rate' => 'decimal:2',
        'digital_penetration' => 'decimal:2',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }
}
