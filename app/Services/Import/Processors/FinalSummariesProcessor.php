<?php

namespace App\Services\Import\Processors;

class FinalSummariesProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'final_summaries';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'business_date',
            'total_sales',
            'modified_order_qty',
            'refunded_order_qty',
            'customer_count',
            'phone_sales',
            'call_center_sales',
            'drive_thru_sales',
            'website_sales',
            'mobile_sales',
            'doordash_sales',
            'grubhub_sales',
            'ubereats_sales',
            'delivery_sales',
            'digital_sales_percent',
            'portal_transactions',
            'put_into_portal',
            'portal_used_percent',
            'put_in_portal_on_time',
            'in_portal_on_time_percent',
            'delivery_tips',
            'prepaid_delivery_tips',
            'in_store_tip_amount',
            'prepaid_in_store_tip_amount',
            'total_tips',
            'over_short',
            'cash_sales',
            'total_waste_cost',
        ];
    }

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'totalsales' => 'total_sales',
            'modifiedorderqty' => 'modified_order_qty',
            'refundedorderqty' => 'refunded_order_qty',
            'customercount' => 'customer_count',
            'phonesales' => 'phone_sales',
            'callcentersales' => 'call_center_sales',
            'drivethrusalse' => 'drive_thru_sales',
            'websitesales' => 'website_sales',
            'mobilesales' => 'mobile_sales',
            'doordashsales' => 'doordash_sales',
            'grubhubsales' => 'grubhub_sales',
            'ubereatsales' => 'ubereats_sales',
            'deliverysales' => 'delivery_sales',
            'digitalsalespercent' => 'digital_sales_percent',
            'portaltransactions' => 'portal_transactions',
            'putintoportal' => 'put_into_portal',
            'portalusedpercent' => 'portal_used_percent',
            'putinportalontime' => 'put_in_portal_on_time',
            'inportalontimepercent' => 'in_portal_on_time_percent',
            'deliverytips' => 'delivery_tips',
            'prepaiddeliverytips' => 'prepaid_delivery_tips',
            'instoretipamount' => 'in_store_tip_amount',
            'prepaidinstoretipamount' => 'prepaid_in_store_tip_amount',
            'totaltips' => 'total_tips',
            'overshort' => 'over_short',
            'cashsales' => 'cash_sales',
            'totalwastecost' => 'total_waste_cost',
        ]);
    }
}
