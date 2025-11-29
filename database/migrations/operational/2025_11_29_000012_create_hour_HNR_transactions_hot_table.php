<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('hour_HNR_transactions_hot', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();
            $table->unsignedTinyInteger('hour')->index();

            $table->integer('transactions')->default(0);
            $table->integer('promise_broken_transactions')->default(0);
            $table->decimal('promise_broken_percentage', 5, 2)->default(0);

            $table->timestamps();

            $table->index(['franchise_store', 'business_date', 'hour'], 'hour_hnr_composite_index');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('hour_HNR_transactions_hot');
    }
};
