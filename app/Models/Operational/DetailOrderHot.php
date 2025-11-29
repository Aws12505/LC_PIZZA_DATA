<?php

namespace App\Models\Operational;

use Illuminate\Database\Eloquent\Relations\HasMany;

class DetailOrderHot extends OperationalModel
{
    protected $table = 'detail_orders_hot';

    protected $fillable = [
        'franchise_store',
        'business_date',
        'order_id',
        'date_time_placed',
        'date_time_fulfilled',
        'royalty_obligation',
        'quantity',
        'customer_count',
        'taxable_amount',
        'non_taxable_amount',
        'tax_exempt_amount',
        'non_royalty_amount',
        'sales_tax',
        'gross_sales',
        'occupational_tax',
        'employee',
        'override_approval_employee',
        'order_placed_method',
        'order_fulfilled_method',
        'delivery_tip',
        'delivery_tip_tax',
        'delivery_fee',
        'delivery_fee_tax',
        'delivery_service_fee',
        'delivery_service_fee_tax',
        'delivery_small_order_fee',
        'delivery_small_order_fee_tax',
        'modified_order_amount',
        'modification_reason',
        'refunded',
        'payment_methods',
        'transaction_type',
        'store_tip_amount',
        'promise_date',
        'tax_exemption_id',
        'tax_exemption_entity_name',
        'user_id',
        'hnrOrder',
        'broken_promise',
        'portal_eligible',
        'portal_used',
        'put_into_portal_before_promise_time',
        'portal_compartments_used',
        'time_loaded_into_portal',
    ];

    protected $casts = [
        'business_date' => 'date',
        'date_time_placed' => 'datetime',
        'date_time_fulfilled' => 'datetime',
        'promise_date' => 'datetime',
        'time_loaded_into_portal' => 'datetime',
        'royalty_obligation' => 'decimal:2',
        'quantity' => 'integer',
        'customer_count' => 'integer',
        'taxable_amount' => 'decimal:2',
        'non_taxable_amount' => 'decimal:2',
        'tax_exempt_amount' => 'decimal:2',
        'non_royalty_amount' => 'decimal:2',
        'sales_tax' => 'decimal:2',
        'gross_sales' => 'decimal:2',
        'occupational_tax' => 'decimal:2',
        'delivery_tip' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'modified_order_amount' => 'decimal:2',
        'store_tip_amount' => 'decimal:2',
    ];

    /**
     * Get the order lines for this order
     */
    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLineHot::class, 'order_id', 'order_id')
            ->where('franchise_store', $this->franchise_store)
            ->where('business_date', $this->business_date);
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
     * Check if order is refunded
     */
    public function isRefunded(): bool
    {
        return strtolower($this->refunded ?? '') === 'yes';
    }

    /**
     * Check if order is modified
     */
    public function isModified(): bool
    {
        return !is_null($this->modified_order_amount);
    }

    /**
     * Check if portal was used
     */
    public function usedPortal(): bool
    {
        return strtolower($this->portal_used ?? '') === 'yes';
    }
}
