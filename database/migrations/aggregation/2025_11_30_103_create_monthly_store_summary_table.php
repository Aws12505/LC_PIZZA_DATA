<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'analytics';

    public function up(): void
    {
        Schema::connection($this->connection)->create('monthly_store_summary', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->year('year_num')->index();
            $table->unsignedTinyInteger('month_num')->index();
            $table->string('month_name', 20);

            // Aggregated metrics
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('net_sales', 15, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->integer('customer_count')->default(0);

            // Operational days
            $table->integer('operational_days')->default(0);
            $table->decimal('avg_daily_sales', 15, 2)->default(0);
            $table->decimal('avg_daily_orders', 10, 2)->default(0);

            // Growth metrics
            $table->decimal('sales_vs_prior_month', 15, 2)->nullable();
            $table->decimal('sales_growth_percent', 5, 2)->nullable();
            $table->decimal('sales_vs_same_month_prior_year', 15, 2)->nullable();
            $table->decimal('yoy_growth_percent', 5, 2)->nullable();

            // Channel metrics
            $table->integer('phone_orders')->default(0);
            $table->decimal('phone_sales', 12, 2)->default(0);
            $table->integer('website_orders')->default(0);
            $table->decimal('website_sales', 12, 2)->default(0);
            $table->integer('mobile_orders')->default(0);
            $table->decimal('mobile_sales', 12, 2)->default(0);
            $table->integer('delivery_orders')->default(0);
            $table->decimal('delivery_sales', 12, 2)->default(0);

            // Product categories
            $table->integer('pizza_quantity')->default(0);
            $table->decimal('pizza_sales', 12, 2)->default(0);
            $table->integer('bread_quantity')->default(0);
            $table->decimal('bread_sales', 12, 2)->default(0);
            $table->integer('wings_quantity')->default(0);
            $table->decimal('wings_sales', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(['franchise_store', 'year_num', 'month_num'], 'unique_monthly_store_summary');
            $table->index(['year_num', 'month_num']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('monthly_store_summary');
    }
};
