<?php

namespace App\Services\Import\Processors;

class WasteProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'waste';
    }

    protected function getUniqueKeys(): array
    {
        return ['business_date', 'franchise_store', 'cv_item_id', 'waste_date_time'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'business_date',
            'franchise_store',
            'cv_item_id',
            'menu_item_name',
            'expired',
            'waste_date_time',
            'produce_date_time',
            'waste_reason',
            'cv_order_id',
            'waste_type',
            'item_cost',
            'quantity',
        ];
    }

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'cvitemid' => 'cv_item_id',
            'menuitemname' => 'menu_item_name',
            'expired' => 'expired',
            'wastedatetime' => 'waste_date_time',
            'producedatetime' => 'produce_date_time',
            'wastereason' => 'waste_reason',
            'cvorderid' => 'cv_order_id',
            'wastetype' => 'waste_type',
            'itemcost' => 'item_cost',
            'quantity' => 'quantity',
        ]);
    }

    protected function transformData(array $row): array
    {
        $row['waste_date_time'] = $this->parseDateTime($row['waste_date_time'] ?? null);
        $row['produce_date_time'] = $this->parseDateTime($row['produce_date_time'] ?? null);
        $row['expired'] = $this->toBoolean($row['expired'] ?? null);
        $row['item_cost'] = $this->toNumeric($row['item_cost'] ?? null);
        $row['quantity'] = $this->toNumeric($row['quantity'] ?? null);

        return $row;
    }
}
