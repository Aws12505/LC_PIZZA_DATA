<?php

namespace App\Models\Operational;

class DeliveryOrderSummaryHot extends OperationalModel
{
    protected $table = 'delivery_order_summary_hot';

    protected $fillable = [
        'business_date',
        'franchise_store',
        'orders_count',
        'product_cost',
        'tax',
        'occupational_tax',
        'delivery_charges',
        'delivery_charges_taxes',
        'service_charges',
        'service_charges_taxes',
        'small_order_charge',
        'small_order_charge_taxes',
        'delivery_late_charge',
        'tip',
        'tip_tax',
        'total_taxes',
        'order_total',
    ];

    protected $casts = [
        'business_date' => 'date',
        'orders_count' => 'integer',
        'product_cost' => 'decimal:2',
        'tax' => 'decimal:2',
        'order_total' => 'decimal:2',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }
}
