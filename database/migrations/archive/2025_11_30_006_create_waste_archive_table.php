<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    protected $connection = 'archive';

    public function up(): void
    {
        Schema::connection($this->connection)->create('waste_archive', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->date('business_date')->index();
            $table->string('franchise_store', 20)->index();
            $table->string('cv_item_id', 10)->nullable();
            $table->string('menu_item_name')->nullable();
            $table->boolean('expired')->nullable();
            $table->timestamp('waste_date_time')->nullable();
            $table->timestamp('produce_date_time')->nullable();
            $table->string('waste_reason')->nullable();
            $table->string('cv_order_id', 10)->nullable();
            $table->string('waste_type', 20)->nullable();
            $table->decimal('item_cost', 8, 4)->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamps();
            $table->primary(['id', 'business_date']);
            $table->index(['franchise_store', 'business_date']);
            $table->unique(['business_date', 'franchise_store', 'cv_item_id', 'waste_date_time'], 'unique_waste_archive');
        });

        // Add monthly partitioning with compression
        $this->addMonthlyPartitions('waste_archive');
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
        Schema::connection($this->connection)->dropIfExists('waste_archive');
    }
};
