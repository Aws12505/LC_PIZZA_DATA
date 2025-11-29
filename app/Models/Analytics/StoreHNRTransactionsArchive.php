<?php

namespace App\Models\Analytics;

class StoreHNRTransactionsArchive extends AnalyticsModel
{
    protected $table = 'store_HNR_transactions_archive';

    protected $fillable = [
        'franchise_store',
        'business_date',
        'item_id',
        'item_name',
        'transactions',
        'promise_met_transactions',
        'promise_met_percentage',
        'transactions_with_CC',
        'promise_met_transactions_cc',
        'promise_met_percentage_cc',
    ];

    protected $casts = [
        'business_date' => 'date',
        'transactions' => 'integer',
        'promise_met_transactions' => 'integer',
        'promise_met_percentage' => 'decimal:2',
        'transactions_with_CC' => 'integer',
        'promise_met_transactions_cc' => 'integer',
        'promise_met_percentage_cc' => 'decimal:2',
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
