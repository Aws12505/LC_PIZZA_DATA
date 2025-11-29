<?php

namespace App\Models\Analytics;

class SummarySalesArchive extends AnalyticsModel
{
    protected $table = 'summary_sales_archive';

    protected $fillable = [
        'franchise_store', 'business_date', 'royalty_obligation', 'customer_count',
        'taxable_amount', 'non_taxable_amount', 'tax_exempt_amount', 'non_royalty_amount',
        'refund_amount', 'sales_tax', 'gross_sales', 'occupational_tax', 'delivery_tip',
        'delivery_fee', 'delivery_service_fee', 'delivery_small_order_fee',
        'modified_order_amount', 'store_tip_amount', 'prepaid_cash_orders',
        'prepaid_non_cash_orders', 'prepaid_sales', 'prepaid_delivery_tip',
        'prepaid_in_store_tip_amount', 'over_short', 'previous_day_refunds', 'saf', 'manager_notes',
    ];

    protected $casts = [
        'business_date' => 'date',
        'customer_count' => 'integer',
        'royalty_obligation' => 'decimal:2',
        'gross_sales' => 'decimal:2',
        'over_short' => 'decimal:2',
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
