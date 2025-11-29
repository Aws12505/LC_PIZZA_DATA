<?php

namespace App\Models\Analytics;

class HourHNRTransactionsArchive extends AnalyticsModel
{
    protected $table = 'hour_HNR_transactions_archive';

    protected $fillable = [
        'franchise_store',
        'business_date',
        'hour',
        'transactions',
        'promise_broken_transactions',
        'promise_broken_percentage',
    ];

    protected $casts = [
        'business_date' => 'date',
        'hour' => 'integer',
        'transactions' => 'integer',
        'promise_broken_transactions' => 'integer',
        'promise_broken_percentage' => 'decimal:2',
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
