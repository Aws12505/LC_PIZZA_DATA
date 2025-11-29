<?php

namespace App\Models\Operational;

class CashManagementHot extends OperationalModel
{
    protected $table = 'cash_management_hot';

    protected $fillable = [
        'franchise_store',
        'business_date',
        'create_datetime',
        'verified_datetime',
        'till',
        'check_type',
        'system_totals',
        'verified',
        'variance',
        'created_by',
        'verified_by',
    ];

    protected $casts = [
        'business_date' => 'date',
        'create_datetime' => 'datetime',
        'verified_datetime' => 'datetime',
        'system_totals' => 'decimal:2',
        'verified' => 'decimal:2',
        'variance' => 'decimal:2',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }

    public function hasVariance(): bool
    {
        return abs($this->variance ?? 0) > 0.01;
    }
}
