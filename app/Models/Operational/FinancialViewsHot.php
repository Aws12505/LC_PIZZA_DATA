<?php

namespace App\Models\Operational;

class FinancialViewsHot extends OperationalModel
{
    protected $table = 'financial_views_hot';

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

    public function scopeForArea($query, $area)
    {
        return $query->where('area', $area);
    }
}
