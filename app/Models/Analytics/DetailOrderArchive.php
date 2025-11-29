<?php

namespace App\Models\Analytics;

class DetailOrderArchive extends AnalyticsModel
{
    protected $table = 'detail_orders_archive';

    // Same fillable as DetailOrderHot
    protected $fillable = [
        'franchise_store', 'business_date', 'order_id', 'date_time_placed',
        'date_time_fulfilled', 'royalty_obligation', 'quantity', 'customer_count',
        'taxable_amount', 'non_taxable_amount', 'tax_exempt_amount', 'non_royalty_amount',
        'sales_tax', 'gross_sales', 'occupational_tax', 'employee',
        'override_approval_employee', 'order_placed_method', 'order_fulfilled_method',
        'delivery_tip', 'delivery_tip_tax', 'delivery_fee', 'delivery_fee_tax',
        'delivery_service_fee', 'delivery_service_fee_tax', 'delivery_small_order_fee',
        'delivery_small_order_fee_tax', 'modified_order_amount', 'modification_reason',
        'refunded', 'payment_methods', 'transaction_type', 'store_tip_amount',
        'promise_date', 'tax_exemption_id', 'tax_exemption_entity_name', 'user_id',
        'hnrOrder', 'broken_promise', 'portal_eligible', 'portal_used',
        'put_into_portal_before_promise_time', 'portal_compartments_used',
        'time_loaded_into_portal',
    ];

    protected $casts = [
        'business_date' => 'date',
        'date_time_placed' => 'datetime',
        'date_time_fulfilled' => 'datetime',
        'gross_sales' => 'decimal:2',
        'customer_count' => 'integer',
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
