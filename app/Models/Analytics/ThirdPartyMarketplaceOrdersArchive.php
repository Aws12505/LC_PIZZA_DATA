<?php

namespace App\Models\Analytics;

class ThirdPartyMarketplaceOrdersArchive extends AnalyticsModel
{
    protected $table = 'third_party_marketplace_orders_archive';

    protected $fillable = [
        'business_date',
        'franchise_store',
        'doordash_product_costs_Marketplace',
        'doordash_tax_Marketplace',
        'doordash_order_total_Marketplace',
        'ubereats_product_costs_Marketplace',
        'ubereats_tax_Marketplace',
        'uberEats_order_total_Marketplace',
        'grubhub_product_costs_Marketplace',
        'grubhub_tax_Marketplace',
        'grubhub_order_total_Marketplace',
    ];

    protected $casts = [
        'business_date' => 'date',
        'doordash_order_total_Marketplace' => 'decimal:2',
        'uberEats_order_total_Marketplace' => 'decimal:2',
        'grubhub_order_total_Marketplace' => 'decimal:2',
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
