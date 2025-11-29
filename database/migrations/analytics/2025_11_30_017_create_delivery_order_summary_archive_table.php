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
        Schema::connection($this->connection)->create('delivery_order_summary_archive', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->date('business_date')->index();
            $table->string('franchise_store', 20)->nullable()->index();
            $table->integer('orders_count')->nullable();
            $table->decimal('product_cost', 10, 2)->nullable();
            $table->decimal('tax', 10, 2)->nullable();
            $table->decimal('occupational_tax', 10, 2)->nullable();
            $table->decimal('delivery_charges', 10, 2)->nullable();
            $table->decimal('delivery_charges_taxes', 10, 2)->nullable();
            $table->decimal('service_charges', 10, 2)->nullable();
            $table->decimal('service_charges_taxes', 10, 2)->nullable();
            $table->decimal('small_order_charge', 10, 2)->nullable();
            $table->decimal('small_order_charge_taxes', 10, 2)->nullable();
            $table->decimal('delivery_late_charge', 10, 2)->nullable();
            $table->decimal('tip', 10, 2)->nullable();
            $table->decimal('tip_tax', 10, 2)->nullable();
            $table->decimal('total_taxes', 10, 2)->nullable();
            $table->decimal('order_total', 10, 2)->nullable();

            $table->timestamps();
            $table->primary(['id', 'business_date']);
            $table->unique(['franchise_store', 'business_date'], 'unique_del_summ_archive');
            $table->index(['franchise_store', 'business_date'],'index_del_summ_archive');
        });

        // Add monthly partitioning with compression
        $this->addMonthlyPartitions('delivery_order_summary_archive');
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
        Schema::connection($this->connection)->dropIfExists('delivery_order_summary_archive');
    }
};
