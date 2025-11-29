<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('store_HNR_transactions_hot', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();
            $table->string('item_id', 10);
            $table->string('item_name');

            $table->integer('transactions')->default(0);
            $table->integer('promise_met_transactions')->default(0);
            $table->decimal('promise_met_percentage', 5, 2)->default(0);
            $table->integer('transactions_with_CC')->default(0);
            $table->integer('promise_met_transactions_cc')->default(0);
            $table->decimal('promise_met_percentage_cc', 5, 2)->default(0);

            $table->timestamps();

            $table->index(['franchise_store', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('store_HNR_transactions_hot');
    }
};
