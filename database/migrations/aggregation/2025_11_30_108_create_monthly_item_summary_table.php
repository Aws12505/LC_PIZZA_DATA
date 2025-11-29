<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'analytics';

    public function up(): void
    {
        Schema::connection($this->connection)->create('monthly_item_summary', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->year('year_num')->index();
            $table->unsignedTinyInteger('month_num')->index();
            $table->string('item_id', 20)->index();
            $table->string('menu_item_name');
            $table->string('menu_item_account');

            // Aggregated sales metrics
            $table->integer('quantity_sold')->default(0);
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('net_sales', 15, 2)->default(0);
            $table->decimal('avg_item_price', 10, 2)->default(0);
            $table->decimal('avg_daily_quantity', 10, 2)->default(0);

            // Growth metrics
            $table->integer('quantity_vs_prior_month')->nullable();
            $table->decimal('quantity_growth_percent', 5, 2)->nullable();
            $table->decimal('sales_vs_prior_month', 15, 2)->nullable();
            $table->decimal('sales_growth_percent', 5, 2)->nullable();

            // Channel breakdown
            $table->integer('delivery_quantity')->default(0);
            $table->integer('carryout_quantity')->default(0);

            $table->timestamps();

            $table->unique(['franchise_store', 'year_num', 'month_num', 'item_id'], 'unique_monthly_item');
            $table->index(['year_num', 'month_num']);
            $table->index('menu_item_account');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('monthly_item_summary');
    }
};
