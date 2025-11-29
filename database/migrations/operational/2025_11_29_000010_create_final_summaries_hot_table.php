<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('final_summaries_hot', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();

            $table->decimal('total_sales', 12, 2)->default(0);
            $table->integer('modified_order_qty')->default(0);
            $table->integer('refunded_order_qty')->default(0);
            $table->integer('customer_count')->default(0);

            $table->decimal('phone_sales', 12, 2)->default(0);
            $table->decimal('call_center_sales', 12, 2)->default(0);
            $table->decimal('drive_thru_sales', 12, 2)->default(0);
            $table->decimal('website_sales', 12, 2)->default(0);
            $table->decimal('mobile_sales', 12, 2)->default(0);

            $table->decimal('doordash_sales', 12, 2)->default(0);
            $table->decimal('grubhub_sales', 12, 2)->default(0);
            $table->decimal('ubereats_sales', 12, 2)->default(0);

            $table->decimal('delivery_sales', 12, 2)->default(0);
            $table->decimal('digital_sales_percent', 5, 2)->default(0);

            $table->integer('portal_transactions')->default(0);
            $table->integer('put_into_portal')->default(0);
            $table->decimal('portal_used_percent', 5, 2)->default(0);
            $table->integer('put_in_portal_on_time')->default(0);
            $table->decimal('in_portal_on_time_percent', 5, 2)->default(0);

            $table->decimal('delivery_tips', 12, 2)->default(0);
            $table->decimal('prepaid_delivery_tips', 12, 2)->default(0);
            $table->decimal('in_store_tip_amount', 12, 2)->default(0);
            $table->decimal('prepaid_instore_tip_amount', 12, 2)->default(0);
            $table->decimal('total_tips', 12, 2)->default(0);

            $table->decimal('over_short', 12, 2)->default(0);
            $table->decimal('cash_sales', 12, 2)->default(0);
            $table->decimal('total_waste_cost', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(['franchise_store', 'business_date'], 'unique_final_summaries_hot');
            $table->index(['franchise_store', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('final_summaries_hot');
    }
};
