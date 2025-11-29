<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('summary_transactions_hot', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();
            $table->string('payment_method', 50)->nullable();
            $table->string('sub_payment_method', 50)->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->integer('saf_qty')->nullable();
            $table->decimal('saf_total', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['franchise_store', 'business_date']);
            $table->unique(['franchise_store', 'business_date', 'payment_method', 'sub_payment_method'], 'unique_summary_transactions_hot');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('summary_transactions_hot');
    }
};
