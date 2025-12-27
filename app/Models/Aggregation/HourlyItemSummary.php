<?php

namespace App\Models\Aggregation;



/**
 * HourlyItemSummary Model
 * 
 * Tracks item-level sales performance aggregated by hour
 * Useful for identifying peak selling times for specific items
 */
class HourlyItemSummary extends AggregationModel
{
    protected $table = 'hourly_item_summary';

    protected $fillable = [
        'franchise_store',
        'business_date',
        'hour',
        'item_id',
        'menu_item_name',
        'menu_item_account',

        // Sales metrics
        'quantity_sold',
        'gross_sales',
        'net_sales',
        'avg_item_price',

        // Fulfillment breakdown
        'delivery_quantity',
        'carryout_quantity',

        // Modifications
        'modified_quantity',
        'refunded_quantity',
    ];

    protected $casts = [
        'business_date' => 'date',
        'hour' => 'integer',

        // Quantities
        'quantity_sold' => 'integer',
        'delivery_quantity' => 'integer',
        'carryout_quantity' => 'integer',
        'modified_quantity' => 'integer',
        'refunded_quantity' => 'integer',

        // Sales
        'gross_sales' => 'decimal:2',
        'net_sales' => 'decimal:2',
        'avg_item_price' => 'decimal:2',
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
     * Scope for specific item
     */
    public function scopeForItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    /**
     * Scope for date range
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('business_date', [$startDate, $endDate]);
    }

    /**
     * Scope for menu item account (e.g., 'Pizza', 'HNR', 'Bread')
     */
    public function scopeForAccount($query, $account)
    {
        return $query->where('menu_item_account', $account);
    }

    /**
     * Scope for business hours
     */
    public function scopeBusinessHours($query, $startHour = 10, $endHour = 22)
    {
        return $query->whereBetween('hour', [$startHour, $endHour]);
    }

    /**
     * Scope for peak hours (lunch and dinner)
     */
    public function scopePeakHours($query)
    {
        return $query->whereIn('hour', [11, 12, 17, 18, 19]);
    }

    /**
     * Scope for top selling items by hour
     */
    public function scopeTopSelling($query, $limit = 10)
    {
        return $query->orderBy('quantity_sold', 'desc')->limit($limit);
    }

    /**
     * Get items with high refund rate
     */
    public function scopeHighRefundRate($query, $threshold = 10)
    {
        return $query->whereRaw('(refunded_quantity / NULLIF(quantity_sold, 0) * 100) > ?', [$threshold]);
    }
}
