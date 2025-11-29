<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('third_party_marketplace_orders_hot', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->nullable()->index();
            $table->string('franchise_store', 20)->nullable()->index();

            $table->decimal('doordash_product_costs_Marketplace', 10, 2)->nullable();
            $table->decimal('doordash_tax_Marketplace', 10, 2)->nullable();
            $table->decimal('doordash_order_total_Marketplace', 10, 2)->nullable();

            $table->decimal('ubereats_product_costs_Marketplace', 10, 2)->nullable();
            $table->decimal('ubereats_tax_Marketplace', 10, 2)->nullable();
            $table->decimal('uberEats_order_total_Marketplace', 10, 2)->nullable();

            $table->decimal('grubhub_product_costs_Marketplace', 10, 2)->nullable();
            $table->decimal('grubhub_tax_Marketplace', 10, 2)->nullable();
            $table->decimal('grubhub_order_total_Marketplace', 10, 2)->nullable();

            $table->timestamps();

            $table->unique(['franchise_store', 'business_date'], 'unique_marketplace_orders_hot');
            $table->index(['franchise_store', 'business_date'],'index_marketplace_orders_hot');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('third_party_marketplace_orders_hot');
    }
};
