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
        'royalty_obligation', 'gross_sales', 'net_sales', 'refund_amount',

        // Order metrics
        'total_orders', 'completed_orders', 'cancelled_orders',
        'modified_orders', 'refunded_orders', 'avg_order_value',
        'customer_count',

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

        // Fulfillment totals (SUM of category splits)
        'delivery_orders', 'delivery_sales',
        'carryout_orders', 'carryout_sales',

        // ✅ Category splits (Delivery vs Carryout)
        'pizza_delivery_quantity', 'pizza_delivery_sales',
        'pizza_carryout_quantity', 'pizza_carryout_sales',

        'hnr_delivery_quantity', 'hnr_delivery_sales',
        'hnr_carryout_quantity', 'hnr_carryout_sales',

        'bread_delivery_quantity', 'bread_delivery_sales',
        'bread_carryout_quantity', 'bread_carryout_sales',

        'wings_delivery_quantity', 'wings_delivery_sales',
        'wings_carryout_quantity', 'wings_carryout_sales',

        'beverages_delivery_quantity', 'beverages_delivery_sales',
        'beverages_carryout_quantity', 'beverages_carryout_sales',

        'other_foods_delivery_quantity', 'other_foods_delivery_sales',
        'other_foods_carryout_quantity', 'other_foods_carryout_sales',

        'side_items_delivery_quantity', 'side_items_delivery_sales',
        'side_items_carryout_quantity', 'side_items_carryout_sales',

        // Financial metrics
        'sales_tax', 'delivery_fees', 'delivery_tips', 'store_tips', 'total_tips',

        // Payment metrics
        'cash_sales', 'over_short',

        // Portal metrics
        'portal_eligible_orders', 'portal_used_orders', 'portal_usage_rate',
        'portal_on_time_orders', 'portal_on_time_rate',

        // Digital metrics
        'digital_orders', 'digital_sales', 'digital_penetration',
    ];

    protected $casts = [
        'business_date' => 'date',
        'hour' => 'integer',

        // Sales metrics
        'royalty_obligation' => 'decimal:2',
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

        // Channels
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

        // Fulfillment totals
        'delivery_orders' => 'integer',
        'delivery_sales' => 'decimal:2',
        'carryout_orders' => 'integer',
        'carryout_sales' => 'decimal:2',

        // Category splits
        'pizza_delivery_quantity' => 'integer',
        'pizza_delivery_sales' => 'decimal:2',
        'pizza_carryout_quantity' => 'integer',
        'pizza_carryout_sales' => 'decimal:2',

        'hnr_delivery_quantity' => 'integer',
        'hnr_delivery_sales' => 'decimal:2',
        'hnr_carryout_quantity' => 'integer',
        'hnr_carryout_sales' => 'decimal:2',

        'bread_delivery_quantity' => 'integer',
        'bread_delivery_sales' => 'decimal:2',
        'bread_carryout_quantity' => 'integer',
        'bread_carryout_sales' => 'decimal:2',

        'wings_delivery_quantity' => 'integer',
        'wings_delivery_sales' => 'decimal:2',
        'wings_carryout_quantity' => 'integer',
        'wings_carryout_sales' => 'decimal:2',

        'beverages_delivery_quantity' => 'integer',
        'beverages_delivery_sales' => 'decimal:2',
        'beverages_carryout_quantity' => 'integer',
        'beverages_carryout_sales' => 'decimal:2',

        'other_foods_delivery_quantity' => 'integer',
        'other_foods_delivery_sales' => 'decimal:2',
        'other_foods_carryout_quantity' => 'integer',
        'other_foods_carryout_sales' => 'decimal:2',

        'side_items_delivery_quantity' => 'integer',
        'side_items_delivery_sales' => 'decimal:2',
        'side_items_carryout_quantity' => 'integer',
        'side_items_carryout_sales' => 'decimal:2',

        // Financial
        'sales_tax' => 'decimal:2',
        'delivery_fees' => 'decimal:2',
        'delivery_tips' => 'decimal:2',
        'store_tips' => 'decimal:2',
        'total_tips' => 'decimal:2',

        // Payments
        'cash_sales' => 'decimal:2',
        'over_short' => 'decimal:2',

        // Portal
        'portal_eligible_orders' => 'integer',
        'portal_used_orders' => 'integer',
        'portal_usage_rate' => 'decimal:2',
        'portal_on_time_orders' => 'integer',
        'portal_on_time_rate' => 'decimal:2',

        // Digital
        'digital_orders' => 'integer',
        'digital_sales' => 'decimal:2',
        'digital_penetration' => 'decimal:2',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('business_date', $date);
    }

    public function scopeForHour($query, $hour)
    {
        return $query->where('hour', $hour);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('business_date', [$startDate, $endDate]);
    }

    public function scopeBusinessHours($query, $startHour = 10, $endHour = 22)
    {
        return $query->whereBetween('hour', [$startHour, $endHour]);
    }

    public function scopePeakHours($query)
    {
        return $query->whereIn('hour', [11, 12, 17, 18, 19]);
    }
}
