<?php

namespace Tests\Feature\API;

use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test data in analytics database
        $this->createTestAggregations();
    }

    /** @test */
    public function it_returns_sales_summary()
    {
        $response = $this->getJson('/api/reports/sales-summary', [
            'store' => '03795',
            'start' => '2025-11-01',
            'end' => '2025-11-30',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'royalty_obligation',
                    'total_orders',
                    'customer_count',
                ]
            ]);
    }

    /** @test */
    public function it_validates_required_parameters()
    {
        $response = $this->getJson('/api/reports/sales-summary');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['store', 'start', 'end']);
    }

    /** @test */
    public function it_returns_daily_breakdown()
    {
        $response = $this->getJson('/api/reports/daily-breakdown', [
            'store' => '03795',
            'start' => '2025-11-01',
            'end' => '2025-11-30',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'count',
                'data',
            ]);
    }

    /** @test */
    public function it_returns_top_items()
    {
        $response = $this->getJson('/api/reports/top-items', [
            'store' => '03795',
            'start' => '2025-11-01',
            'end' => '2025-11-30',
            'limit' => 10,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'count',
                'data',
            ]);
    }

    /** @test */
    public function it_returns_channel_performance()
    {
        $response = $this->getJson('/api/reports/channel-performance', [
            'store' => '03795',
            'start' => '2025-11-01',
            'end' => '2025-11-30',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'channels',
                    'royalty_obligation'
                ]
            ]);
    }

    /** @test */
    public function it_validates_date_range()
    {
        $response = $this->getJson('/api/reports/sales-summary', [
            'store' => '03795',
            'start' => '2025-11-30',
            'end' => '2025-11-01', // End before start
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end']);
    }

    protected function createTestAggregations(): void
    {
        // Create test daily summaries
        for ($i = 1; $i <= 30; $i++) {
            DB::connection('analytics')->table('daily_store_summary')->insert([
                'franchise_store' => '03795',
                'business_date' => Carbon::parse("2025-11-{$i}")->toDateString(),
                'total_sales' => rand(1000, 5000),
                'total_orders' => rand(50, 200),
                'customer_count' => rand(100, 400),
                'avg_order_value' => rand(20, 50),
            ]);
        }

        // Create test item summaries
        DB::connection('analytics')->table('daily_item_summary')->insert([
            'franchise_store' => '03795',
            'business_date' => '2025-11-15',
            'item_id' => 'ITEM001',
            'menu_item_name' => 'Pepperoni Pizza',
            'quantity_sold' => 50,
            'gross_sales' => 500.00,
            'avg_item_price' => 10.00,
        ]);
    }
}
