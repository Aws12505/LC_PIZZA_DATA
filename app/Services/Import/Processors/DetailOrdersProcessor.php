<?php

namespace App\Services\Import\Processors;

class DetailOrdersProcessor extends BaseTableProcessor
{
    protected function getTableName(): string
    {
        return 'detail_orders';
    }

    protected function getImportStrategy(): string
    {
        return self::STRATEGY_UPSERT;
    }

    protected function getUniqueKeys(): array
    {
        return ['franchise_store', 'business_date', 'order_id', 'transaction_type'];
    }

    protected function getFillableColumns(): array
    {
        return [
            'franchise_store',
            'business_date',
            'order_id',
            'date_time_placed',
            'date_time_fulfilled',
            'royalty_obligation',
            'quantity',
            'customer_count',
            'taxable_amount',
            'non_taxable_amount',
            'tax_exempt_amount',
            'non_royalty_amount',
            'sales_tax',
            'gross_sales',
            'occupational_tax',
            'employee',
            'override_approval_employee',
            'order_placed_method',
            'order_fulfilled_method',
            'delivery_tip',
            'delivery_tip_tax',
            'delivery_fee',
            'delivery_fee_tax',
            'delivery_service_fee',
            'delivery_service_fee_tax',
            'delivery_small_order_fee',
            'delivery_small_order_fee_tax',
            'modified_order_amount',
            'modification_reason',
            'refunded',
            'payment_methods',
            'transaction_type',
            'store_tip_amount',
            'promise_date',
            'tax_exemption_id',
            'tax_exemption_entity_name',
            'user_id',
            'hnrOrder',
            'broken_promise',
            'portal_eligible',
            'portal_used',
            'put_into_portal_before_promise_time',
            'portal_compartments_used',
            'time_loaded_into_portal',
        ];
    }

    protected function getColumnMapping(): array
    {
        return array_merge(parent::getColumnMapping(), [
            'orderid' => 'order_id',
            'datetimeplaced' => 'date_time_placed',
            'datetimefulfilled' => 'date_time_fulfilled',
            'royaltyobligation' => 'royalty_obligation',
            'quantity' => 'quantity',
            'customercount' => 'customer_count',
            'taxableamount' => 'taxable_amount',
            'nontaxableamount' => 'non_taxable_amount',
            'taxexemptamount' => 'tax_exempt_amount',
            'nonroyaltyamount' => 'non_royalty_amount',
            'salestax' => 'sales_tax',
            'grosssales' => 'gross_sales',
            'occupationaltax' => 'occupational_tax',
            'employee' => 'employee',
            'overrideapprovalemployee' => 'override_approval_employee',
            'orderplacedmethod' => 'order_placed_method',
            'orderfulfilledmethod' => 'order_fulfilled_method',
            'deliverytip' => 'delivery_tip',
            'deliverytiptax' => 'delivery_tip_tax',
            'deliveryfee' => 'delivery_fee',
            'deliveryfeetax' => 'delivery_fee_tax',
            'deliveryservicefee' => 'delivery_service_fee',
            'deliveryservicefeetax' => 'delivery_service_fee_tax',
            'deliverysmallorderfee' => 'delivery_small_order_fee',
            'deliverysmallorderfeetax' => 'delivery_small_order_fee_tax',
            'modifiedorderamount' => 'modified_order_amount',
            'modificationreason' => 'modification_reason',
            'refunded' => 'refunded',
            'paymentmethods' => 'payment_methods',
            'transactiontype' => 'transaction_type',
            'storetipamount' => 'store_tip_amount',
            'promisedate' => 'promise_date',
            'taxexemptionid' => 'tax_exemption_id',
            'taxexemptionentityname' => 'tax_exemption_entity_name',
            'userid' => 'user_id',
            'hnrorder' => 'hnrOrder',
            'brokenpromise' => 'broken_promise',
            'portaleligible' => 'portal_eligible',
            'portalused' => 'portal_used',
            'putintoportalbeforepromisetime' => 'put_into_portal_before_promise_time',
            'portalcompartmentsused' => 'portal_compartments_used',
            'timeloadedintoportal' => 'time_loaded_into_portal',
        ]);
    }

    protected function transformData(array $row): array
    {
        $row['date_time_placed'] = $this->parseDateTime($row['date_time_placed'] ?? null);
        $row['date_time_fulfilled'] = $this->parseDateTime($row['date_time_fulfilled'] ?? null);
        $row['promise_date'] = $this->parseDateTime($row['promise_date'] ?? null);
        $row['time_loaded_into_portal'] = $this->parseDateTime($row['time_loaded_into_portal'] ?? null);

        foreach (['royalty_obligation', 'quantity', 'customer_count', 'gross_sales'] as $field) {
            $row[$field] = $this->toNumeric($row[$field] ?? null);
        }

        return $row;
    }
}
