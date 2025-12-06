<?php

namespace App\Services\Import\Processors;

class ThirdPartyMarketplaceOrdersProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'third_party_marketplace_orders';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'business_date',
            'franchise_store',
            'doordash_product_costs_marketplace',
            'doordash_tax_Marketplace',
            'doordash_order_total_Marketplace',
            'ubereats_product_costs_Marketplace',
            'ubereats_tax_Marketplace',
            'uberEats_order_total_Marketplace',
            'grubhub_product_costs_Marketplace',
            'grubhub_tax_Marketplace',
            'grubhub_order_total_Marketplace',
        ];
    }

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'doordashproductcostsmarketplace' => 'doordash_product_costs_marketplace',
            'doordashtaxmarketplace' => 'doordash_tax_Marketplace',
            'doordashordertotalmarketplace' => 'doordash_order_total_Marketplace',
            'ubereatsproductcostsmarketplace' => 'ubereats_product_costs_Marketplace',
            'ubereatstaxmarketplace' => 'ubereats_tax_Marketplace',
            'ubereatsordertotalmarketplace' => 'uberEats_order_total_Marketplace',
            'grubhubproductcostsmarketplace' => 'grubhub_product_costs_Marketplace',
            'grubhubtaxmarketplace' => 'grubhub_tax_Marketplace',
            'grubhubordertotalmarketplace' => 'grubhub_order_total_Marketplace',
        ]);
    }
}
