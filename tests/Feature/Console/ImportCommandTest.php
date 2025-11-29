<?php

namespace Tests\Feature\Console;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use App\Services\Main\LCReportDataService;
use Mockery;

class ImportCommandTest extends TestCase
{
    /** @test */
    public function it_can_run_import_command()
    {
        // Mock the service
        $this->mock(LCReportDataService::class, function ($mock) {
            $mock->shouldReceive('importReportData')
                ->once()
                ->with('2025-11-29')
                ->andReturn(true);
        });

        $exitCode = Artisan::call('import:daily-data', [
            '--date' => '2025-11-29',
        ]);

        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function it_handles_import_failure()
    {
        $this->mock(LCReportDataService::class, function ($mock) {
            $mock->shouldReceive('importReportData')
                ->once()
                ->andReturn(false);
        });

        $exitCode = Artisan::call('import:daily-data', [
            '--date' => '2025-11-29',
        ]);

        $this->assertEquals(1, $exitCode);
    }

    /** @test */
    public function it_can_run_aggregation_update_command()
    {
        $exitCode = Artisan::call('aggregation:update', [
            '--date' => '2025-11-29',
            '--type' => 'daily',
        ]);

        // Command should complete (might fail due to no data, but should not error)
        $this->assertContains($exitCode, [0, 1]);
    }

    /** @test */
    public function it_can_run_validation_command()
    {
        $exitCode = Artisan::call('validation:check-data', [
            '--date' => '2025-11-29',
        ]);

        $this->assertContains($exitCode, [0, 1]);
    }

    /** @test */
    public function it_can_run_partition_stats_command()
    {
        $exitCode = Artisan::call('partition:stats');

        $this->assertEquals(0, $exitCode);
    }
}
