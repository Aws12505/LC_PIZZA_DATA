<?php

namespace App\Models\Analytics;

class OrderLineArchive extends AnalyticsModel
{
    protected $table = 'order_line_archive';

    protected $fillable = [
        'franchise_store',
        'business_date',
        'order_id',
        'item_id',
        'date_time_placed',
        'date_time_fulfilled',
        'menu_item_name',
        'menu_item_account',
        'bundle_name',
        'net_amount',
        'quantity',
        'royalty_item',
        'taxable_item',
        'tax_included_amount',
        'employee',
        'override_approval_employee',
        'order_placed_method',
        'order_fulfilled_method',
        'modified_order_amount',
        'modification_reason',
        'payment_methods',
        'refunded',
    ];

    protected $casts = [
        'business_date' => 'date',
        'date_time_placed' => 'datetime',
        'date_time_fulfilled' => 'datetime',
        'net_amount' => 'decimal:2',
        'quantity' => 'integer',
        'tax_included_amount' => 'decimal:2',
        'modified_order_amount' => 'decimal:2',
        // Generated columns (read-only)
        'is_pizza' => 'boolean',
        'is_bread' => 'boolean',
        'is_wings' => 'boolean',
        'is_beverages' => 'boolean',
        'is_crazy_puffs' => 'boolean',
        'is_caesar_dip' => 'boolean',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('business_date', [$startDate, $endDate]);
    }
}
