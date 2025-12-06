<?php

namespace App\Services\Import\Processors;

class AltaInventoryCogsProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'alta_inventory_cogs';
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'count_period', 'inventory_category'];
    }

    protected function getFillableColumns(): array
    {
        return [
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
    }

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'countperiod' => 'count_period',
            'inventorycategory' => 'inventory_category',
            'startingvalue' => 'starting_value',
            'receivedvalue' => 'received_value',
            'nettransfervalue' => 'net_transfer_value',
            'endingvalue' => 'ending_value',
            'usedvalue' => 'used_value',
            'theoreticalusagevalue' => 'theoretical_usage_value',
            'variancevalue' => 'variance_value',
        ]);
    }
}
