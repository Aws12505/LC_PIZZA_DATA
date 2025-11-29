<?php

namespace App\Models\Operational;

class WasteHot extends OperationalModel
{
    protected $table = 'waste_hot';

    protected $fillable = [
        'business_date', 'franchise_store', 'cv_item_id', 'menu_item_name',
        'expired', 'waste_date_time', 'produce_date_time', 'waste_reason',
        'cv_order_id', 'waste_type', 'item_cost', 'quantity',
    ];

    protected $casts = [
        'business_date' => 'date',
        'expired' => 'boolean',
        'waste_date_time' => 'datetime',
        'produce_date_time' => 'datetime',
        'item_cost' => 'decimal:4',
        'quantity' => 'integer',
    ];
}
