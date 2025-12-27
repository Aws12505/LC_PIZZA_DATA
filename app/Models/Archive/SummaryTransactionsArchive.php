<?php

namespace App\Models\Archive;

class SummaryTransactionsArchive extends ArchiveModel
{
    protected $table = 'summary_transactions_archive';

    protected $fillable = [
        'franchise_store',
        'business_date',
        'payment_method',
        'sub_payment_method',
        'total_amount',
        'saf_qty',
        'saf_total',
    ];

    protected $casts = [
        'business_date' => 'date',
        'total_amount' => 'decimal:2',
        'saf_qty' => 'integer',
        'saf_total' => 'decimal:2',
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
