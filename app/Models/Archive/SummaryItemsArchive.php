<?php

namespace App\Models\Archive;

class SummaryItemsArchive extends ArchiveModel
{
    protected $table = 'summary_items_archive';

    protected $fillable = [
        'franchise_store', 'business_date', 'menu_item_name', 'menu_item_account',
        'item_id', 'item_quantity', 'royalty_obligation', 'taxable_amount',
        'non_taxable_amount', 'tax_exempt_amount', 'non_royalty_amount', 'tax_included_amount',
    ];

    protected $casts = [
        'business_date' => 'date',
        'item_quantity' => 'integer',
        'royalty_obligation' => 'decimal:2',
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
