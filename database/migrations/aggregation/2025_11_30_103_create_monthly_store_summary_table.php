<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'aggregation';

    public function up(): void
    {
        Schema::connection($this->connection)->create('monthly_store_summary', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->year('year_num')->index();
            $table->unsignedTinyInteger('month_num')->index();
            $table->string('month_name', 20);

            // SALES
            $table->decimal('royalty_obligation', 15, 2)->default(0);
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('net_sales', 15, 2)->default(0);
            $table->decimal('refund_amount', 15, 2)->default(0);

            // ORDERS
            $table->integer('total_orders')->default(0);
            $table->integer('completed_orders')->default(0);
            $table->integer('cancelled_orders')->default(0);
            $table->integer('modified_orders')->default(0);
            $table->integer('refunded_orders')->default(0);
            $table->decimal('avg_order_value', 10, 2)->default(0);
            $table->integer('customer_count')->default(0);

            // Operational days / averages
            $table->integer('operational_days')->default(0);
            $table->decimal('avg_daily_sales', 15, 2)->default(0);
            $table->decimal('avg_daily_orders', 10, 2)->default(0);

            // Growth metrics
            $table->decimal('sales_vs_prior_month', 15, 2)->nullable();
            $table->decimal('sales_growth_percent', 15, 2)->nullable();
            $table->decimal('sales_vs_same_month_prior_year', 15, 2)->nullable();
            $table->decimal('yoy_growth_percent', 15, 2)->nullable();

            // CHANNEL ORDERS
            $table->integer('phone_orders')->default(0);
            $table->integer('website_orders')->default(0);
            $table->integer('mobile_orders')->default(0);
            $table->integer('call_center_orders')->default(0);
            $table->integer('drive_thru_orders')->default(0);

            // CHANNEL SALES
            $table->decimal('phone_sales', 12, 2)->default(0);
            $table->decimal('website_sales', 12, 2)->default(0);
            $table->decimal('mobile_sales', 12, 2)->default(0);
            $table->decimal('call_center_sales', 12, 2)->default(0);
            $table->decimal('drive_thru_sales', 12, 2)->default(0);

            // MARKETPLACE
            $table->integer('doordash_orders')->default(0);
            $table->decimal('doordash_sales', 12, 2)->default(0);
            $table->integer('ubereats_orders')->default(0);
            $table->decimal('ubereats_sales', 12, 2)->default(0);
            $table->integer('grubhub_orders')->default(0);
            $table->decimal('grubhub_sales', 12, 2)->default(0);

            // FULFILLMENT TOTALS (sum of category splits)
            $table->integer('delivery_orders')->default(0);
            $table->decimal('delivery_sales', 12, 2)->default(0);
            $table->integer('carryout_orders')->default(0);
            $table->decimal('carryout_sales', 12, 2)->default(0);

            // ✅ CATEGORY SPLITS
            // Pizza
            $table->integer('pizza_delivery_quantity')->default(0);
            $table->decimal('pizza_delivery_sales', 12, 2)->default(0);
            $table->integer('pizza_carryout_quantity')->default(0);
            $table->decimal('pizza_carryout_sales', 12, 2)->default(0);

            // HNR
            $table->integer('hnr_delivery_quantity')->default(0);
            $table->decimal('hnr_delivery_sales', 12, 2)->default(0);
            $table->integer('hnr_carryout_quantity')->default(0);
            $table->decimal('hnr_carryout_sales', 12, 2)->default(0);

            // Bread
            $table->integer('bread_delivery_quantity')->default(0);
            $table->decimal('bread_delivery_sales', 12, 2)->default(0);
            $table->integer('bread_carryout_quantity')->default(0);
            $table->decimal('bread_carryout_sales', 12, 2)->default(0);

            // Wings
            $table->integer('wings_delivery_quantity')->default(0);
            $table->decimal('wings_delivery_sales', 12, 2)->default(0);
            $table->integer('wings_carryout_quantity')->default(0);
            $table->decimal('wings_carryout_sales', 12, 2)->default(0);

            // Beverages
            $table->integer('beverages_delivery_quantity')->default(0);
            $table->decimal('beverages_delivery_sales', 12, 2)->default(0);
            $table->integer('beverages_carryout_quantity')->default(0);
            $table->decimal('beverages_carryout_sales', 12, 2)->default(0);

            // Other Foods
            $table->integer('other_foods_delivery_quantity')->default(0);
            $table->decimal('other_foods_delivery_sales', 12, 2)->default(0);
            $table->integer('other_foods_carryout_quantity')->default(0);
            $table->decimal('other_foods_carryout_sales', 12, 2)->default(0);

            // Side Items
            $table->integer('side_items_delivery_quantity')->default(0);
            $table->decimal('side_items_delivery_sales', 12, 2)->default(0);
            $table->integer('side_items_carryout_quantity')->default(0);
            $table->decimal('side_items_carryout_sales', 12, 2)->default(0);

            // FINANCIAL
            $table->decimal('sales_tax', 12, 2)->default(0);
            $table->decimal('delivery_fees', 12, 2)->default(0);
            $table->decimal('delivery_tips', 12, 2)->default(0);
            $table->decimal('store_tips', 12, 2)->default(0);
            $table->decimal('total_tips', 12, 2)->default(0);

            // PAYMENTS
            $table->decimal('cash_sales', 12, 2)->default(0);
            $table->decimal('over_short', 12, 2)->default(0);

            // PORTAL
            $table->integer('portal_eligible_orders')->default(0);
            $table->integer('portal_used_orders')->default(0);
            $table->decimal('portal_usage_rate', 5, 2)->default(0);
            $table->integer('portal_on_time_orders')->default(0);
            $table->decimal('portal_on_time_rate', 5, 2)->default(0);

            // DIGITAL
            $table->integer('digital_orders')->default(0);
            $table->decimal('digital_sales', 12, 2)->default(0);
            $table->decimal('digital_penetration', 5, 2)->default(0);

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
