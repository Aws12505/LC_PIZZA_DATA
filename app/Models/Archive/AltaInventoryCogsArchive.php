<?php

namespace App\Models\Archive;

class AltaInventoryCogsArchive extends ArchiveModel
{
    protected $table = 'alta_inventory_cogs_archive';

    protected $fillable = [
        'franchise_store',
        'business_date',
        'count_period',
        'inventory_category',
        'starting_value',
        'received_value',
        'net_transfer_value',
        'ending_value',
        'used_value',
        'theoretical_usage_value',
        'variance_value',
    ];

    protected $casts = [
        'business_date' => 'date',
        'starting_value' => 'decimal:2',
        'received_value' => 'decimal:2',
        'ending_value' => 'decimal:2',
        'used_value' => 'decimal:2',
        'variance_value' => 'decimal:2',
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
