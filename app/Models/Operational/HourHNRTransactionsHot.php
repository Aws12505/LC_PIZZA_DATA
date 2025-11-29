<?php

namespace App\Models\Operational;

class HourHNRTransactionsHot extends OperationalModel
{
    protected $table = 'hour_HNR_transactions_hot';

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

    public function scopeForHour($query, $hour)
    {
        return $query->where('hour', $hour);
    }
}
