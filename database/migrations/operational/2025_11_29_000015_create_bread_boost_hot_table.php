<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('bread_boost_hot', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->index();
            $table->string('franchise_store', 20)->index();
            $table->integer('classic_order')->nullable();
            $table->integer('classic_with_bread')->nullable();
            $table->integer('other_pizza_order')->nullable();
            $table->integer('other_pizza_with_bread')->nullable();

            $table->timestamps();

            $table->unique(['franchise_store', 'business_date'], 'unique_bread_boost_hot');
            $table->index(['franchise_store', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('bread_boost_hot');
    }
};
