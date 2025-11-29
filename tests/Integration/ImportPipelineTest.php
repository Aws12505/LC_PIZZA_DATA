<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Services\Main\LCReportDataService;
use App\Services\API\PureIO;
use App\Services\API\Networked;
use App\Services\Aggregation\AggregationService;
use Mockery;

class ImportPipelineTest extends TestCase
{
    /** @test */
    public function it_processes_csv_and_imports_data()
    {
        // Mock the API calls
        $pureIO = Mockery::mock(PureIO::class);
        $networked = Mockery::mock(Networked::class);
        $aggregationService = Mockery::mock(AggregationService::class);

        // Create actual CSV file for testing
        $csvContent = "franchise_store,business_date,order_id,gross_sales\n";
        $csvContent .= "03795,2025-11-29,ORD001,25.99\n";
        $csvContent .= "03795,2025-11-29,ORD002,35.50\n";

        $csvPath = storage_path('app/test_detail_orders.csv');
        file_put_contents($csvPath, $csvContent);

        // Test CSV reading and processing
        $service = new LCReportDataService($pureIO, $networked, $aggregationService);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('readCsvFile');
        $method->setAccessible(true);

        $data = $method->invoke($service, $csvPath);

        $this->assertCount(2, $data);
        $this->assertEquals('03795', $data[0]['franchise_store']);
        $this->assertEquals('25.99', $data[0]['gross_sales']);

        // Cleanup
        @unlink($csvPath);
    }

    /** @test */
    public function it_handles_malformed_csv()
    {
        $pureIO = Mockery::mock(PureIO::class);
        $networked = Mockery::mock(Networked::class);
        $aggregationService = Mockery::mock(AggregationService::class);

        // Create malformed CSV
        $csvContent = "franchise_store,business_date\n";
        $csvContent .= "03795,2025-11-29,EXTRA_COLUMN\n"; // Wrong column count

        $csvPath = storage_path('app/test_malformed.csv');
        file_put_contents($csvPath, $csvContent);

        $service = new LCReportDataService($pureIO, $networked, $aggregationService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('readCsvFile');
        $method->setAccessible(true);

        $data = $method->invoke($service, $csvPath);

        // Should skip malformed rows
        $this->assertEmpty($data);

        @unlink($csvPath);
    }
}
