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
        Schema::connection($this->connection)->create('alta_inventory_ingredient_orders_archive', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('franchise_store', 20)->nullable();
            $table->date('business_date');
            $table->string('supplier', 50)->nullable();
            $table->string('invoice_number', 50)->nullable();
            $table->string('purchase_order_number', 50)->nullable();
            $table->string('ingredient_id', 50)->nullable();
            $table->string('ingredient_description')->nullable();
            $table->string('ingredient_category')->nullable();
            $table->string('ingredient_unit', 50)->nullable();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('order_qty', 10, 2)->default(0);
            $table->decimal('sent_qty', 10, 2)->default(0);
            $table->decimal('received_qty', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);

            $table->timestamps();
            $table->primary(['id', 'business_date']);
            $table->unique(['franchise_store', 'business_date', 'supplier', 'invoice_number', 'purchase_order_number', 'ingredient_id'], 'inv_ing_orders_arch');
        });

        // Add monthly partitioning with compression
        $this->addMonthlyPartitions('alta_inventory_ingredient_orders_archive');
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
        Schema::connection($this->connection)->dropIfExists('alta_inventory_ingredient_orders_archive');
    }
};
