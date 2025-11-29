<?php

namespace App\Models\Operational;

class HourlySalesHot extends OperationalModel
{
    protected $table = 'hourly_sales_hot';

    protected $fillable = [
        'franchise_store', 'business_date', 'hour', 'total_sales', 'phone_sales',
        'call_center_sales', 'drive_thru_sales', 'website_sales', 'mobile_sales',
        'website_sales_delivery', 'mobile_sales_delivery', 'doordash_sales',
        'ubereats_sales', 'grubhub_sales', 'order_count',
    ];

    protected $casts = [
        'business_date' => 'date',
        'hour' => 'integer',
        'total_sales' => 'decimal:2',
        'order_count' => 'integer',
    ];

    public function scopeForHour($query, $hour)
    {
        return $query->where('hour', $hour);
    }
}
