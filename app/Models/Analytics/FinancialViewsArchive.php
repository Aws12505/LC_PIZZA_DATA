<?php

namespace App\Models\Analytics;

class FinancialViewsArchive extends AnalyticsModel
{
    protected $table = 'financial_views_archive';

    protected $fillable = [
        'franchise_store',
        'business_date',
        'area',
        'sub_account',
        'amount',
    ];

    protected $casts = [
        'business_date' => 'date',
        'amount' => 'decimal:2',
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
