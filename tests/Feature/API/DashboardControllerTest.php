<?php

namespace Tests\Feature\API;

use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_returns_dashboard_overview()
    {
        $response = $this->getJson('/api/dashboard/overview', [
            'store' => '03795',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'today',
                    'yesterday',
                    'week',
                    'month',
                ]
            ]);
    }

    /** @test */
    public function it_returns_dashboard_stats()
    {
        $response = $this->getJson('/api/dashboard/stats', [
            'store' => '03795',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'last_import',
                    'data_health',
                    'partition_info',
                ]
            ]);
    }

    /** @test */
    public function it_validates_required_store_parameter()
    {
        $response = $this->getJson('/api/dashboard/overview');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['store']);
    }

    protected function createDataForTest(): void
    {
        // Create data for today
        DB::connection('analytics')->table('daily_store_summary')->insert([
            'franchise_store' => '03795',
            'business_date' => Carbon::today()->toDateString(),
            'total_sales' => 2500.00,
            'total_orders' => 100,
            'customer_count' => 200,
            'avg_order_value' => 25.00,
            'digital_penetration' => 45.5,
        ]);

        // Create data for yesterday
        DB::connection('analytics')->table('daily_store_summary')->insert([
            'franchise_store' => '03795',
            'business_date' => Carbon::yesterday()->toDateString(),
            'total_sales' => 2300.00,
            'total_orders' => 95,
            'customer_count' => 190,
            'avg_order_value' => 24.21,
            'digital_penetration' => 43.2,
        ]);

        // Create operational data for health check
        DB::connection('operational')->table('detail_orders_hot')->insert([
            'franchise_store' => '03795',
            'business_date' => Carbon::yesterday()->toDateString(),
            'order_id' => 'TEST001',
            'gross_sales' => 25.99,
        ]);
    }
}
