<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    protected $connection = 'analytics';

    public function up(): void
    {
        Schema::connection($this->connection)->create('daily_store_summary', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();

            // SALES METRICS
            $table->decimal('total_sales', 15, 2)->default(0);
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

            // CUSTOMER METRICS
            $table->integer('customer_count')->default(0);
            $table->decimal('avg_customers_per_order', 5, 2)->default(0);

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

            // FINANCIAL METRICS
            $table->decimal('sales_tax', 12, 2)->default(0);
            $table->decimal('delivery_fees', 12, 2)->default(0);
            $table->decimal('delivery_tips', 12, 2)->default(0);
            $table->decimal('store_tips', 12, 2)->default(0);
            $table->decimal('total_tips', 12, 2)->default(0);

            // PAYMENT METRICS
            $table->decimal('cash_sales', 12, 2)->default(0);
            $table->decimal('credit_card_sales', 12, 2)->default(0);
            $table->decimal('prepaid_sales', 12, 2)->default(0);
            $table->decimal('over_short', 12, 2)->default(0);

            // OPERATIONAL METRICS
            $table->integer('portal_eligible_orders')->default(0);
            $table->integer('portal_used_orders')->default(0);
            $table->decimal('portal_usage_rate', 5, 2)->default(0);
            $table->integer('portal_on_time_orders')->default(0);
            $table->decimal('portal_on_time_rate', 5, 2)->default(0);

            // WASTE METRICS
            $table->integer('total_waste_items')->default(0);
            $table->decimal('total_waste_cost', 12, 2)->default(0);

            // DIGITAL METRICS
            $table->integer('digital_orders')->default(0);
            $table->decimal('digital_sales', 12, 2)->default(0);
            $table->decimal('digital_penetration', 5, 2)->default(0);

            $table->timestamps();
            $table->primary(['id', 'business_date']);
            $table->unique(['franchise_store', 'business_date'], 'unique_daily_store_summary');
            $table->index(['franchise_store', 'business_date']);
        });

        // Add monthly partitioning
        $this->addMonthlyPartitions('daily_store_summary');
    }

    protected function addMonthlyPartitions($tableName): void
    {
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
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('daily_store_summary');
    }
};
