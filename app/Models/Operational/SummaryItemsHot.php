<?php

namespace App\Models\Operational;

class SummaryItemsHot extends OperationalModel
{
    protected $table = 'summary_items_hot';

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
}
