<?php

namespace App\Models\Operational;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLineHot extends OperationalModel
{
    protected $table = 'order_line_hot';

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

    /**
     * The attributes that should be appended to the model's array form.
     */
    protected $appends = ['is_pizza', 'is_bread', 'is_wings', 'is_beverages'];

    /**
     * Get the parent order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(DetailOrderHot::class, 'order_id', 'order_id')
            ->where('franchise_store', $this->franchise_store)
            ->where('business_date', $this->business_date);
    }

    /**
     * Scope for specific product categories
     */
    public function scopePizza($query)
    {
        return $query->where('is_pizza', true);
    }

    public function scopeBread($query)
    {
        return $query->where('is_bread', true);
    }

    public function scopeWings($query)
    {
        return $query->where('is_wings', true);
    }

    public function scopeBeverages($query)
    {
        return $query->where('is_beverages', true);
    }

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
     * Check if item is refunded
     */
    public function isRefunded(): bool
    {
        return strtolower($this->refunded ?? '') === 'yes';
    }
}
