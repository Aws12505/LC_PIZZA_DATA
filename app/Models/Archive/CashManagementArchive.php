<?php

namespace App\Models\Archive;

class CashManagementArchive extends ArchiveModel
{
    protected $table = 'cash_management_archive';

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

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('business_date', [$startDate, $endDate]);
    }
}
