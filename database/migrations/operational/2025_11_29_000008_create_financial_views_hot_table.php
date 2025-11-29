<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('financial_views_hot', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();
            $table->string('area', 70)->nullable();
            $table->string('sub_account', 50)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['franchise_store', 'business_date']);
            $table->unique(['franchise_store', 'business_date', 'sub_account', 'area'], 'unique_financial_views_hot');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('financial_views_hot');
    }
};
