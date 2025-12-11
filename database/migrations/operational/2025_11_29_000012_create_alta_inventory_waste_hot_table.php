<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('alta_inventory_waste_hot', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->nullable();
            $table->date('business_date')->nullable();
            $table->string('item_id', 10)->nullable();
            $table->string('item_description')->nullable();
            $table->string('waste_reason')->nullable();
            $table->decimal('unit_food_cost', 10, 2)->default(0);
            $table->decimal('qty', 10, 2)->default(0);

            $table->timestamps();

            $table->unique(['franchise_store', 'business_date', 'item_id'], 'inv_waste_unique_hot');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('alta_inventory_waste_hot');
    }
};
