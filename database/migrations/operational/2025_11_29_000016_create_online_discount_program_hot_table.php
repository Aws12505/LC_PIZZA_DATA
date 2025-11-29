<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('online_discount_program_hot', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->nullable();
            $table->bigInteger('order_id')->nullable();
            $table->date('business_date')->nullable();
            $table->string('pay_type', 20)->nullable();
            $table->decimal('original_subtotal', 10, 2)->nullable();
            $table->decimal('modified_subtotal', 10, 2)->nullable();
            $table->string('promo_code')->nullable();

            $table->timestamps();

            $table->unique(['franchise_store', 'business_date', 'order_id'], 'unique_discount_program_hot');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('online_discount_program_hot');
    }
};
