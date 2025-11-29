<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\Database\DatabaseRouter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DatabaseRouterTest extends TestCase
{
    /** @test */
    public function it_routes_to_operational_for_recent_dates()
    {
        $date = Carbon::now()->subDays(30);

        $db = DatabaseRouter::getDatabase($date);

        $this->assertEquals('operational', $db);
    }

    /** @test */
    public function it_routes_to_analytics_for_old_dates()
    {
        $date = Carbon::now()->subDays(100);

        $db = DatabaseRouter::getDatabase($date);

        $this->assertEquals('analytics', $db);
    }

    /** @test */
    public function it_gets_hot_table_name_for_recent_dates()
    {
        $date = Carbon::now()->subDays(30);

        $tableName = DatabaseRouter::getTableName('detail_orders', $date);

        $this->assertEquals('detail_orders_hot', $tableName);
    }

    /** @test */
    public function it_gets_archive_table_name_for_old_dates()
    {
        $date = Carbon::now()->subDays(100);

        $tableName = DatabaseRouter::getTableName('detail_orders', $date);

        $this->assertEquals('detail_orders_archive', $tableName);
    }

    /** @test */
    public function it_detects_when_query_spans_both_databases()
    {
        $startDate = Carbon::now()->subDays(100);
        $endDate = Carbon::now()->subDays(30);

        $spans = DatabaseRouter::spansDatabase($startDate, $endDate);

        $this->assertTrue($spans);
    }

    /** @test */
    public function it_detects_when_query_does_not_span_databases()
    {
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $spans = DatabaseRouter::spansDatabase($startDate, $endDate);

        $this->assertFalse($spans);
    }

    /** @test */
    public function it_gets_cutoff_date()
    {
        $cutoff = DatabaseRouter::getCutoffDate();

        $expectedCutoff = Carbon::now()->subDays(90);

        $this->assertEquals($expectedCutoff->toDateString(), $cutoff->toDateString());
    }
}
