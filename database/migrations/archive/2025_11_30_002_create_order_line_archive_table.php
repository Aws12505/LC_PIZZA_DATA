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
        Schema::connection($this->connection)->create('order_line_archive', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();

            // Core identifiers
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();
            $table->string('order_id', 20)->nullable();
            $table->string('item_id', 20)->nullable();

            // Timestamps
            $table->dateTime('date_time_placed')->nullable();
            $table->dateTime('date_time_fulfilled')->nullable();

            // Item info
            $table->string('menu_item_name')->nullable();
            $table->string('menu_item_account')->nullable();
            $table->string('bundle_name')->nullable();

            // Quantities and amounts
            $table->decimal('net_amount', 15, 2)->nullable();
            $table->integer('quantity')->nullable();
            $table->string('royalty_item', 10)->nullable();
            $table->string('taxable_item', 10)->nullable();
            $table->decimal('tax_included_amount', 15, 2)->nullable();

            // Employee info
            $table->string('employee')->nullable();
            $table->string('override_approval_employee')->nullable();

            // Order methods
            $table->string('order_placed_method', 50)->nullable();
            $table->string('order_fulfilled_method', 50)->nullable();

            // Modifications
            $table->decimal('modified_order_amount', 15, 2)->nullable();
            $table->string('modification_reason')->nullable();
            $table->string('payment_methods', 100)->nullable();
            $table->string('refunded', 50)->nullable();

            // Laravel timestamps
            $table->timestamps();
            $table->primary(['id', 'business_date']);
            // Indexes
            $table->index(['franchise_store', 'business_date']);
            $table->index('menu_item_account');
        });

        // Add generated columns
        DB::connection($this->connection)->statement("
            ALTER TABLE order_line_archive
            ADD COLUMN is_pizza BOOLEAN GENERATED ALWAYS AS (menu_item_account IN ('HNR', 'Pizza')) STORED,
            ADD COLUMN is_bread BOOLEAN GENERATED ALWAYS AS (menu_item_account = 'Bread') STORED,
            ADD COLUMN is_wings BOOLEAN GENERATED ALWAYS AS (menu_item_account = 'Wings') STORED,
            ADD COLUMN is_beverages BOOLEAN GENERATED ALWAYS AS (menu_item_account = 'Beverages') STORED,
            ADD COLUMN is_crazy_puffs BOOLEAN GENERATED ALWAYS AS (
                (menu_item_name LIKE '%Puffs%') OR 
                (item_id IN ('103057', '103044', '103033'))
            ) STORED,
            ADD COLUMN is_caesar_dip BOOLEAN GENERATED ALWAYS AS (
                item_id IN ('206117', '206103', '206104', '206108', '206101')
            ) STORED
        ");

        // Add monthly partitioning with compression
        $this->addMonthlyPartitions('order_line_archive');
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
        Schema::connection($this->connection)->dropIfExists('order_line_archive');
    }
};
