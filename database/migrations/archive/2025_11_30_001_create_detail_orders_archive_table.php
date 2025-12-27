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
        // Create table with same structure as hot table
        Schema::connection($this->connection)->create('detail_orders_archive', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();

            // Core identifiers
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();
            $table->string('order_id', 20)->nullable();

            // Timestamps
            $table->dateTime('date_time_placed')->nullable();
            $table->dateTime('date_time_fulfilled')->nullable();

            // Financial metrics
            $table->decimal('royalty_obligation', 15, 2)->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('customer_count')->nullable();
            $table->decimal('taxable_amount', 15, 2)->nullable();
            $table->decimal('non_taxable_amount', 15, 2)->nullable();
            $table->decimal('tax_exempt_amount', 15, 2)->nullable();
            $table->decimal('non_royalty_amount', 15, 2)->nullable();
            $table->decimal('sales_tax', 15, 2)->nullable();
            $table->decimal('gross_sales', 15, 2)->nullable();
            $table->decimal('occupational_tax', 15, 2)->nullable();

            // Employee info
            $table->string('employee')->nullable();
            $table->string('override_approval_employee')->nullable();

            // Order methods
            $table->string('order_placed_method', 50)->nullable();
            $table->string('order_fulfilled_method', 50)->nullable();

            // Delivery info
            $table->decimal('delivery_tip', 15, 2)->nullable();
            $table->decimal('delivery_tip_tax', 15, 2)->nullable();
            $table->decimal('delivery_fee', 15, 2)->nullable();
            $table->decimal('delivery_fee_tax', 15, 2)->nullable();
            $table->decimal('delivery_service_fee', 15, 2)->nullable();
            $table->decimal('delivery_service_fee_tax', 15, 2)->nullable();
            $table->decimal('delivery_small_order_fee', 15, 2)->nullable();
            $table->decimal('delivery_small_order_fee_tax', 15, 2)->nullable();

            // Modifications
            $table->decimal('modified_order_amount', 15, 2)->nullable();
            $table->string('modification_reason')->nullable();
            $table->string('refunded', 20)->nullable();

            // Payment
            $table->string('payment_methods', 255)->nullable();

            // Transaction info
            $table->string('transaction_type', 50)->nullable();
            $table->decimal('store_tip_amount', 15, 2)->nullable();
            $table->dateTime('promise_date')->nullable();

            // Tax exemption
            $table->string('tax_exemption_id', 20)->nullable();
            $table->string('tax_exemption_entity_name')->nullable();

            // User & portal info
            $table->string('user_id', 50)->nullable();
            $table->string('hnrOrder', 5)->nullable();
            $table->string('broken_promise', 5)->nullable();
            $table->string('portal_eligible', 5)->nullable();
            $table->string('portal_used', 5)->nullable();
            $table->string('put_into_portal_before_promise_time', 5)->nullable();
            $table->string('portal_compartments_used', 10)->nullable();
            $table->dateTime('time_loaded_into_portal')->nullable();

            // Laravel timestamps
            $table->timestamps();

            $table->primary(['id', 'business_date']);
            // Indexes (fewer than hot table since archive is read-heavy)
            $table->index(['franchise_store', 'business_date']);
            $table->unique(['franchise_store', 'business_date', 'order_id', 'transaction_type'], 'unique_detail_orders_archive');
        });

        // Add monthly partitioning with compression
        $this->addMonthlyPartitions('detail_orders_archive');
    }

    protected function addMonthlyPartitions($tableName): void
    {
        // Generate partitions from 2020-01 to current month + 3 months future
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

        // Enable compression for archive table
        if (config('features.archive_compression_enabled', true)) {
            DB::connection($this->connection)->statement(
                "ALTER TABLE {$tableName} ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8"
            );
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('detail_orders_archive');
    }
};
