<?php

namespace App\Models\Operational;

class FinanceDataHot extends OperationalModel
{
    protected $table = 'finance_data_hot';

    protected $fillable = [
        'franchise_store', 'business_date',
        'Pizza_Carryout', 'HNR_Carryout', 'Bread_Carryout', 'Wings_Carryout',
        'Beverages_Carryout', 'Other_Foods_Carryout', 'Side_Items_Carryout',
        'Pizza_Delivery', 'HNR_Delivery', 'Bread_Delivery', 'Wings_Delivery',
        'Beverages_Delivery', 'Other_Foods_Delivery', 'Side_Items_Delivery',
        'Delivery_Charges', 'TOTAL_Net_Sales', 'Customer_Count',
        'Gift_Card_Non_Royalty', 'Total_Non_Royalty_Sales', 'Total_Non_Delivery_Tips',
        'Sales_Tax_Food_Beverage', 'Sales_Tax_Delivery', 'TOTAL_Sales_TaxQuantity',
        'DELIVERY_Quantity', 'Delivery_Fee', 'Delivery_Service_Fee',
        'Delivery_Small_Order_Fee', 'Delivery_Late_to_Portal_Fee',
        'TOTAL_Native_App_Delivery_Fees', 'Delivery_Tips',
        'DoorDash_Quantity', 'DoorDash_Order_Total', 'Grubhub_Quantity',
        'Grubhub_Order_Total', 'Uber_Eats_Quantity', 'Uber_Eats_Order_Total',
        'ONLINE_ORDERING_Mobile_Order_Quantity', 'ONLINE_ORDERING_Online_Order_Quantity',
        'ONLINE_ORDERING_Pay_In_Store', 'Agent_Pre_Paid', 'Agent_Pay_InStore',
        'AI_Pre_Paid', 'AI_Pay_InStore', 'PrePaid_Cash_Orders', 'PrePaid_Non_Cash_Orders',
        'PrePaid_Sales', 'Prepaid_Delivery_Tips', 'Prepaid_InStore_Tips',
        'Marketplace_from_Non_Cash_Payments_box', 'AMEX', 'Total_Non_Cash_Payments',
        'credit_card_Cash_Payments', 'Debit_Cash_Payments', 'epay_Cash_Payments',
        'Non_Cash_Payments', 'Cash_Sales', 'Cash_Drop_Total', 'Over_Short', 'Payouts',
    ];

    protected $casts = [
        'business_date' => 'date',
        'TOTAL_Net_Sales' => 'decimal:2',
        'Customer_Count' => 'integer',
        'DELIVERY_Quantity' => 'integer',
        'DoorDash_Quantity' => 'integer',
        'Grubhub_Quantity' => 'integer',
        'Uber_Eats_Quantity' => 'integer',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }
}
