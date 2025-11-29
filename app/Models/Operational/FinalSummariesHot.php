<?php

namespace App\Models\Operational;

class FinalSummariesHot extends OperationalModel
{
    protected $table = 'final_summaries_hot';

    protected $fillable = [
        'franchise_store', 'business_date', 'total_sales', 'modified_order_qty',
        'refunded_order_qty', 'customer_count', 'phone_sales', 'call_center_sales',
        'drive_thru_sales', 'website_sales', 'mobile_sales', 'doordash_sales',
        'grubhub_sales', 'ubereats_sales', 'delivery_sales', 'digital_sales_percent',
        'portal_transactions', 'put_into_portal', 'portal_used_percent',
        'put_in_portal_on_time', 'in_portal_on_time_percent', 'delivery_tips',
        'prepaid_delivery_tips', 'in_store_tip_amount', 'prepaid_instore_tip_amount',
        'total_tips', 'over_short', 'cash_sales', 'total_waste_cost',
    ];

    protected $casts = [
        'business_date' => 'date',
        'total_sales' => 'decimal:2',
        'customer_count' => 'integer',
        'portal_transactions' => 'integer',
    ];
}
