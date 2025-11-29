<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('hourly_sales_hot', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();
            $table->unsignedTinyInteger('hour')->index();

            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('phone_sales', 12, 2)->default(0);
            $table->decimal('call_center_sales', 12, 2)->default(0);
            $table->decimal('drive_thru_sales', 12, 2)->default(0);
            $table->decimal('website_sales', 12, 2)->default(0);
            $table->decimal('mobile_sales', 12, 2)->default(0);
            $table->decimal('website_sales_delivery', 12, 2)->nullable();
            $table->decimal('mobile_sales_delivery', 12, 2)->nullable();
            $table->decimal('doordash_sales', 12, 2)->nullable();
            $table->decimal('ubereats_sales', 12, 2)->nullable();
            $table->decimal('grubhub_sales', 12, 2)->nullable();
            $table->integer('order_count')->nullable();

            $table->timestamps();

            $table->unique(['franchise_store', 'business_date', 'hour'], 'unique_hourly_sales_hot');
            $table->index(['franchise_store', 'business_date', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('hourly_sales_hot');
    }
};
