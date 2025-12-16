<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('detail_orders_hot', function (Blueprint $table) {
            $table->id();

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

            // Indexes
            $table->index(['franchise_store', 'business_date']);
            $table->unique(['franchise_store', 'business_date', 'order_id', 'transaction_type'], 'unique_detail_orders_hot');
        });

        // Add daily partitioning (will be managed by partition command)
        // Initial partition setup will be done after table creation
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('detail_orders_hot');
    }
};
