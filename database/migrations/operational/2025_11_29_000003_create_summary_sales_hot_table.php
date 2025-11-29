<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('summary_sales_hot', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();

            $table->decimal('royalty_obligation', 15, 2)->nullable();
            $table->integer('customer_count')->nullable();
            $table->decimal('taxable_amount', 15, 2)->nullable();
            $table->decimal('non_taxable_amount', 15, 2)->nullable();
            $table->decimal('tax_exempt_amount', 15, 2)->nullable();
            $table->decimal('non_royalty_amount', 15, 2)->nullable();
            $table->decimal('refund_amount', 15, 2)->nullable();
            $table->decimal('sales_tax', 15, 2)->nullable();
            $table->decimal('gross_sales', 15, 2)->nullable();
            $table->decimal('occupational_tax', 15, 2)->nullable();
            $table->decimal('delivery_tip', 15, 2)->nullable();
            $table->decimal('delivery_fee', 15, 2)->nullable();
            $table->decimal('delivery_service_fee', 15, 2)->nullable();
            $table->decimal('delivery_small_order_fee', 15, 2)->nullable();
            $table->decimal('modified_order_amount', 15, 2)->nullable();
            $table->decimal('store_tip_amount', 15, 2)->nullable();
            $table->decimal('prepaid_cash_orders', 15, 2)->nullable();
            $table->decimal('prepaid_non_cash_orders', 15, 2)->nullable();
            $table->decimal('prepaid_sales', 15, 2)->nullable();
            $table->decimal('prepaid_delivery_tip', 15, 2)->nullable();
            $table->decimal('prepaid_in_store_tip_amount', 15, 2)->nullable();
            $table->decimal('over_short', 15, 2)->nullable();
            $table->decimal('previous_day_refunds', 15, 2)->nullable();
            $table->string('saf', 50)->nullable();
            $table->string('manager_notes', 50)->nullable();

            $table->timestamps();

            $table->index(['franchise_store', 'business_date']);
            $table->unique(['franchise_store', 'business_date'], 'unique_summary_sales_hot');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('summary_sales_hot');
    }
};
