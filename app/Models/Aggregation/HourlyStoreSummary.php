<?php

namespace App\Models\Aggregation;



/**
 * HourlyStoreSummary Model
 * 
 * Tracks store performance metrics aggregated by hour
 * ~24 records per store per day
 */
class HourlyStoreSummary extends AggregationModel
{
    protected $table = 'hourly_store_summary';

    protected $fillable = [
        'franchise_store', 'business_date', 'hour',

        // Sales metrics
        'total_sales', 'gross_sales', 'net_sales', 'refund_amount',

        // Order metrics
        'total_orders', 'completed_orders', 'cancelled_orders',
        'modified_orders', 'refunded_orders', 'avg_order_value',
        'customer_count', 'avg_customers_per_order',

        // Channel metrics - Orders
        'phone_orders', 'website_orders', 'mobile_orders',
        'call_center_orders', 'drive_thru_orders',

        // Channel metrics - Sales
        'phone_sales', 'website_sales', 'mobile_sales',
        'call_center_sales', 'drive_thru_sales',

        // Marketplace metrics
        'doordash_orders', 'doordash_sales',
        'ubereats_orders', 'ubereats_sales',
        'grubhub_orders', 'grubhub_sales',

        // Fulfillment metrics
        'delivery_orders', 'delivery_sales',
        'carryout_orders', 'carryout_sales',

        // Product category metrics
        'pizza_quantity', 'pizza_sales',
        'hnr_quantity', 'hnr_sales',
        'bread_quantity', 'bread_sales',
        'wings_quantity', 'wings_sales',
        'beverages_quantity', 'beverages_sales',
        'crazy_puffs_quantity', 'crazy_puffs_sales',

        // Financial metrics
        'sales_tax', 'delivery_fees', 'delivery_tips', 'store_tips', 'total_tips',

        // Payment metrics
        'cash_sales', 'credit_card_sales', 'prepaid_sales', 'over_short',

        // Portal metrics
        'portal_eligible_orders', 'portal_used_orders', 'portal_usage_rate',
        'portal_on_time_orders', 'portal_on_time_rate',

        // Waste metrics
        'total_waste_items', 'total_waste_cost',

        // Digital metrics
        'digital_orders', 'digital_sales', 'digital_penetration',
    ];

    protected $casts = [
        'business_date' => 'date',
        'hour' => 'integer',

        // Sales metrics
        'total_sales' => 'decimal:2',
        'gross_sales' => 'decimal:2',
        'net_sales' => 'decimal:2',
        'refund_amount' => 'decimal:2',

        // Order metrics
        'total_orders' => 'integer',
        'completed_orders' => 'integer',
        'cancelled_orders' => 'integer',
        'modified_orders' => 'integer',
        'refunded_orders' => 'integer',
        'avg_order_value' => 'decimal:2',
        'customer_count' => 'integer',
        'avg_customers_per_order' => 'decimal:2',

        // Channel metrics
        'phone_orders' => 'integer',
        'phone_sales' => 'decimal:2',
        'website_orders' => 'integer',
        'website_sales' => 'decimal:2',
        'mobile_orders' => 'integer',
        'mobile_sales' => 'decimal:2',
        'call_center_orders' => 'integer',
        'call_center_sales' => 'decimal:2',
        'drive_thru_orders' => 'integer',
        'drive_thru_sales' => 'decimal:2',

        // Marketplace
        'doordash_orders' => 'integer',
        'doordash_sales' => 'decimal:2',
        'ubereats_orders' => 'integer',
        'ubereats_sales' => 'decimal:2',
        'grubhub_orders' => 'integer',
        'grubhub_sales' => 'decimal:2',

        // Fulfillment
        'delivery_orders' => 'integer',
        'delivery_sales' => 'decimal:2',
        'carryout_orders' => 'integer',
        'carryout_sales' => 'decimal:2',

        // Products
        'pizza_quantity' => 'integer',
        'pizza_sales' => 'decimal:2',
        'hnr_quantity' => 'integer',
        'hnr_sales' => 'decimal:2',
        'bread_quantity' => 'integer',
        'bread_sales' => 'decimal:2',
        'wings_quantity' => 'integer',
        'wings_sales' => 'decimal:2',
        'beverages_quantity' => 'integer',
        'beverages_sales' => 'decimal:2',
        'crazy_puffs_quantity' => 'integer',
        'crazy_puffs_sales' => 'decimal:2',

        // Financial
        'sales_tax' => 'decimal:2',
        'delivery_fees' => 'decimal:2',
        'delivery_tips' => 'decimal:2',
        'store_tips' => 'decimal:2',
        'total_tips' => 'decimal:2',

        // Payments
        'cash_sales' => 'decimal:2',
        'credit_card_sales' => 'decimal:2',
        'prepaid_sales' => 'decimal:2',
        'over_short' => 'decimal:2',

        // Portal
        'portal_eligible_orders' => 'integer',
        'portal_used_orders' => 'integer',
        'portal_usage_rate' => 'decimal:2',
        'portal_on_time_orders' => 'integer',
        'portal_on_time_rate' => 'decimal:2',

        // Waste
        'total_waste_items' => 'integer',
        'total_waste_cost' => 'decimal:2',

        // Digital
        'digital_orders' => 'integer',
        'digital_sales' => 'decimal:2',
        'digital_penetration' => 'decimal:2',
    ];

    /**
     * Scope for specific store
     */
    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }

    /**
     * Scope for specific date
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('business_date', $date);
    }

    /**
     * Scope for specific hour
     */
    public function scopeForHour($query, $hour)
    {
        return $query->where('hour', $hour);
    }

    /**
     * Scope for date range
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('business_date', [$startDate, $endDate]);
    }

    /**
     * Scope for business hours (e.g., 10 AM to 10 PM)
     */
    public function scopeBusinessHours($query, $startHour = 10, $endHour = 22)
    {
        return $query->whereBetween('hour', [$startHour, $endHour]);
    }

    /**
     * Scope for peak hours
     */
    public function scopePeakHours($query)
    {
        return $query->whereIn('hour', [11, 12, 17, 18, 19]); // Lunch and dinner rush
    }
}
