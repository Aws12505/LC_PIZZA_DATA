<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\Import\Processors\DetailOrdersProcessor;
use Illuminate\Support\Facades\DB;

class BaseTableProcessorTest extends TestCase
{
    /** @test */
    public function it_can_process_and_import_data()
    {
        $processor = new DetailOrdersProcessor();

        $testData = [
            [
                'franchise_store' => '03795',
                'business_date' => '2025-11-29',
                'order_id' => 'ORD001',
                'gross_sales' => '25.99',
                'customer_count' => '2',
            ]
        ];

        $count = $processor->process($testData, '2025-11-29');

        $this->assertEquals(1, $count);

        $this->assertOperationalDatabaseHas('detail_orders_hot', [
            'franchise_store' => '03795',
            'order_id' => 'ORD001',
        ]);
    }

    /** @test */
    public function it_transforms_numeric_values()
    {
        $processor = new DetailOrdersProcessor();

        $testData = [
            [
                'franchise_store' => '03795',
                'business_date' => '2025-11-29',
                'order_id' => 'ORD001',
                'gross_sales' => '25.99',
                'customer_count' => '2',
            ]
        ];

        $processor->process($testData, '2025-11-29');

        $record = DB::connection('operational')
            ->table('detail_orders_hot')
            ->where('order_id', 'ORD001')
            ->first();

        $this->assertIsFloat($record->gross_sales);
        $this->assertEquals(25.99, $record->gross_sales);
    }

    /** @test */
    public function it_handles_empty_data()
    {
        $processor = new DetailOrdersProcessor();

        $count = $processor->process([], '2025-11-29');

        $this->assertEquals(0, $count);
    }

    /** @test */
    public function it_upserts_on_duplicate_keys()
    {
        $processor = new DetailOrdersProcessor();

        // First insert
        $testData = [
            [
                'franchise_store' => '03795',
                'business_date' => '2025-11-29',
                'order_id' => 'ORD001',
                'gross_sales' => '25.99',
            ]
        ];

        $processor->process($testData, '2025-11-29');

        // Update same record
        $testData[0]['gross_sales'] = '30.00';
        $processor->process($testData, '2025-11-29');

        $record = DB::connection('operational')
            ->table('detail_orders_hot')
            ->where('order_id', 'ORD001')
            ->first();

        $this->assertEquals(30.00, $record->gross_sales);

        // Should only have one record
        $count = DB::connection('operational')
            ->table('detail_orders_hot')
            ->where('order_id', 'ORD001')
            ->count();

        $this->assertEquals(1, $count);
    }
}
