<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('cash_management_hot', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();
            $table->dateTime('create_datetime')->nullable();
            $table->dateTime('verified_datetime')->nullable();
            $table->string('till', 100)->nullable();
            $table->string('check_type')->nullable();
            $table->decimal('system_totals', 15, 2)->nullable();
            $table->decimal('verified', 15, 2)->nullable();
            $table->decimal('variance', 15, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->string('verified_by')->nullable();
            $table->timestamps();

            $table->index(['franchise_store', 'business_date']);
            $table->unique(['franchise_store', 'business_date', 'create_datetime', 'till', 'check_type'], 'unique_cash_management_hot');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('cash_management_hot');
    }
};
