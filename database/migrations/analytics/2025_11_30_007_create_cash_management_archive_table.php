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
        Schema::connection($this->connection)->create('cash_management_archive', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();
            $table->dateTime('create_datetime')->nullable();
            $table->dateTime('verified_datetime')->nullable();
            $table->string('till', 20)->nullable();
            $table->string('check_type')->nullable();
            $table->decimal('system_totals', 15, 2)->nullable();
            $table->decimal('verified', 15, 2)->nullable();
            $table->decimal('variance', 15, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->string('verified_by')->nullable();
            $table->timestamps();
            $table->primary(['id', 'business_date']);
            $table->index(['franchise_store', 'business_date']);
            $table->unique(['franchise_store', 'business_date', 'create_datetime', 'till', 'check_type'], 'unique_cash_mgmt_archive');
        });

        // Add monthly partitioning with compression
        $this->addMonthlyPartitions('cash_management_archive');
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
        Schema::connection($this->connection)->dropIfExists('cash_management_archive');
    }
};
