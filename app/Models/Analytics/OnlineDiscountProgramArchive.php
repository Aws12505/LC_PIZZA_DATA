<?php

namespace App\Models\Analytics;

class OnlineDiscountProgramArchive extends AnalyticsModel
{
    protected $table = 'online_discount_program_archive';

    protected $fillable = [
        'franchise_store',
        'order_id',
        'business_date',
        'pay_type',
        'original_subtotal',
        'modified_subtotal',
        'promo_code',
    ];

    protected $casts = [
        'business_date' => 'date',
        'order_id' => 'integer',
        'original_subtotal' => 'decimal:2',
        'modified_subtotal' => 'decimal:2',
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
