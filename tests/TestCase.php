<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Use testing database connections
        config([
            'database.connections.operational.database' => 'testing_operational',
            'database.connections.analytics.database' => 'testing_analytics',
        ]);
    }

    /**
     * Create test data in operational database
     */
    protected function createTestData(string $table, array $data): void
    {
        DB::connection('operational')->table($table)->insert($data);
    }

    /**
     * Create test data in analytics database
     */
    protected function createAnalyticsData(string $table, array $data): void
    {
        DB::connection('analytics')->table($table)->insert($data);
    }

    /**
     * Assert database has record in operational DB
     */
    protected function assertOperationalDatabaseHas(string $table, array $data): void
    {
        $this->assertDatabaseHas($table, $data, 'operational');
    }

    /**
     * Assert database has record in analytics DB
     */
    protected function assertAnalyticsDatabaseHas(string $table, array $data): void
    {
        $this->assertDatabaseHas($table, $data, 'analytics');
    }
}
