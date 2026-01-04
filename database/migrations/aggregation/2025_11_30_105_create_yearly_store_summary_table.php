<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'aggregation';

    public function up(): void
    {
        Schema::connection($this->connection)->create('yearly_store_summary', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->year('year_num')->index();

            // SALES METRICS
            $table->decimal('royalty_obligation', 15, 2)->default(0);
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('net_sales', 15, 2)->default(0);
            $table->decimal('refund_amount', 15, 2)->default(0);

            // ORDER METRICS
            $table->integer('total_orders')->default(0);
            $table->integer('completed_orders')->default(0);
            $table->integer('cancelled_orders')->default(0);
            $table->integer('modified_orders')->default(0);
            $table->integer('refunded_orders')->default(0);
            $table->decimal('avg_order_value', 10, 2)->default(0);
            $table->integer('customer_count')->default(0);

            // Operational metrics
            $table->integer('operational_days')->default(0);
            $table->integer('operational_months')->default(0);
            $table->decimal('avg_daily_sales', 15, 2)->default(0);
            $table->decimal('avg_monthly_sales', 15, 2)->default(0);

            // Growth metrics
            $table->decimal('sales_vs_prior_year', 15, 2)->nullable();
            $table->decimal('sales_growth_percent', 15, 2)->nullable();

            // CHANNEL METRICS - Orders
            $table->integer('phone_orders')->default(0);
            $table->integer('website_orders')->default(0);
            $table->integer('mobile_orders')->default(0);
            $table->integer('call_center_orders')->default(0);
            $table->integer('drive_thru_orders')->default(0);

            // CHANNEL METRICS - Sales
            $table->decimal('phone_sales', 12, 2)->default(0);
            $table->decimal('website_sales', 12, 2)->default(0);
            $table->decimal('mobile_sales', 12, 2)->default(0);
            $table->decimal('call_center_sales', 12, 2)->default(0);
            $table->decimal('drive_thru_sales', 12, 2)->default(0);

            // MARKETPLACE METRICS
            $table->integer('doordash_orders')->default(0);
            $table->decimal('doordash_sales', 12, 2)->default(0);
            $table->integer('ubereats_orders')->default(0);
            $table->decimal('ubereats_sales', 12, 2)->default(0);
            $table->integer('grubhub_orders')->default(0);
            $table->decimal('grubhub_sales', 12, 2)->default(0);

            // FULFILLMENT METRICS
            $table->integer('delivery_orders')->default(0);
            $table->decimal('delivery_sales', 12, 2)->default(0);
            $table->integer('carryout_orders')->default(0);
            $table->decimal('carryout_sales', 12, 2)->default(0);

            // PRODUCT CATEGORY METRICS
            $table->integer('pizza_quantity')->default(0);
            $table->decimal('pizza_sales', 12, 2)->default(0);
            $table->integer('hnr_quantity')->default(0);
            $table->decimal('hnr_sales', 12, 2)->default(0);
            $table->integer('bread_quantity')->default(0);
            $table->decimal('bread_sales', 12, 2)->default(0);
            $table->integer('wings_quantity')->default(0);
            $table->decimal('wings_sales', 12, 2)->default(0);
            $table->integer('beverages_quantity')->default(0);
            $table->decimal('beverages_sales', 12, 2)->default(0);
            $table->integer('crazy_puffs_quantity')->default(0);
            $table->decimal('crazy_puffs_sales', 12, 2)->default(0);

            // FINANCIAL METRICS
            $table->decimal('sales_tax', 12, 2)->default(0);
            $table->decimal('delivery_fees', 12, 2)->default(0);
            $table->decimal('delivery_tips', 12, 2)->default(0);
            $table->decimal('store_tips', 12, 2)->default(0);
            $table->decimal('total_tips', 12, 2)->default(0);

            // PAYMENT METRICS
            $table->decimal('cash_sales', 12, 2)->default(0);

            $table->decimal('over_short', 12, 2)->default(0);

            // OPERATIONAL METRICS
            $table->integer('portal_eligible_orders')->default(0);
            $table->integer('portal_used_orders')->default(0);
            $table->decimal('portal_usage_rate', 5, 2)->default(0);
            $table->integer('portal_on_time_orders')->default(0);
            $table->decimal('portal_on_time_rate', 5, 2)->default(0);

            // WASTE METRICS


            // DIGITAL METRICS
            $table->integer('digital_orders')->default(0);
            $table->decimal('digital_sales', 12, 2)->default(0);
            $table->decimal('digital_penetration', 5, 2)->default(0);

            $table->timestamps();

            $table->unique(['franchise_store', 'year_num'], 'unique_yearly_store_summary');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('yearly_store_summary');
    }
};
