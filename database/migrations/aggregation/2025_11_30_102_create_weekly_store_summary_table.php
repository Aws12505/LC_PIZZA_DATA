<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'analytics';

    public function up(): void
    {
        Schema::connection($this->connection)->create('weekly_store_summary', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->year('year_num')->index();
            $table->unsignedTinyInteger('week_num')->index();
            $table->date('week_start_date');
            $table->date('week_end_date');

            // Same metrics as daily_store_summary but aggregated
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('net_sales', 15, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->integer('customer_count')->default(0);
            $table->decimal('avg_daily_sales', 15, 2)->default(0);
            $table->decimal('avg_daily_orders', 10, 2)->default(0);

            // Growth metrics
            $table->decimal('sales_vs_prior_week', 15, 2)->nullable();
            $table->decimal('sales_growth_percent', 5, 2)->nullable();
            $table->integer('orders_vs_prior_week')->nullable();
            $table->decimal('orders_growth_percent', 5, 2)->nullable();

            // All channel metrics (same structure as daily)
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

            $table->unique(['franchise_store', 'year_num', 'week_num'], 'unique_weekly_store_summary');
            $table->index(['year_num', 'week_num']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('weekly_store_summary');
    }
};
