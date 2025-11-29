<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('delivery_order_summary_hot', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->nullable()->index();
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

            $table->unique(['franchise_store', 'business_date'], 'unique_delivery_summary_hot');
            $table->index(['franchise_store', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('delivery_order_summary_hot');
    }
};
