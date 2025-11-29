<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    protected $connection = 'analytics';

    public function up(): void
    {
        Schema::connection($this->connection)->create('hourly_sales_archive', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();
            $table->unsignedTinyInteger('hour')->index();

            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('phone_sales', 12, 2)->default(0);
            $table->decimal('call_center_sales', 12, 2)->default(0);
            $table->decimal('drive_thru_sales', 12, 2)->default(0);
            $table->decimal('website_sales', 12, 2)->default(0);
            $table->decimal('mobile_sales', 12, 2)->default(0);
            $table->decimal('website_sales_delivery', 12, 2)->nullable();
            $table->decimal('mobile_sales_delivery', 12, 2)->nullable();
            $table->decimal('doordash_sales', 12, 2)->nullable();
            $table->decimal('ubereats_sales', 12, 2)->nullable();
            $table->decimal('grubhub_sales', 12, 2)->nullable();
            $table->integer('order_count')->nullable();

            $table->timestamps();
            $table->primary(['id', 'business_date']);
            $table->unique(['franchise_store', 'business_date', 'hour'], 'unique_hourly_sales_archive');
            $table->index(['franchise_store', 'business_date', 'hour']);
        });

        // Add monthly partitioning with compression
        $this->addMonthlyPartitions('hourly_sales_archive');
    }

    protected function addMonthlyPartitions($tableName): void
    {
        $startDate = Carbon::create(2020, 1, 1);
        $endDate = Carbon::now()->addMonths(3)->endOfMonth();

        $partitions = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $partName = 'p' . $current->format('Ym');
            $nextMonth = $current->copy()->addMonth();
            $partValue = $nextMonth->year * 100 + $nextMonth->month;

            $partitions[] = "PARTITION {$partName} VALUES LESS THAN ({$partValue})";
            $current->addMonth();
        }

        $partitions[] = "PARTITION p_future VALUES LESS THAN MAXVALUE";

        $partitionSql = "ALTER TABLE {$tableName} 
            PARTITION BY RANGE (YEAR(business_date) * 100 + MONTH(business_date)) (" 
            . implode(", ", $partitions) . ")";

        DB::connection($this->connection)->statement($partitionSql);

        if (config('features.archive_compression_enabled', true)) {
            DB::connection($this->connection)->statement(
                "ALTER TABLE {$tableName} ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8"
            );
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('hourly_sales_archive');
    }
};
