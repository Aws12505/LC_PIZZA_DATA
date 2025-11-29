<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('alta_inventory_cogs_hot', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->nullable();
            $table->date('business_date')->nullable();
            $table->string('count_period', 20)->nullable();
            $table->string('inventory_category')->nullable();
            $table->decimal('starting_value', 10, 2)->default(0);
            $table->decimal('received_value', 10, 2)->default(0);
            $table->decimal('net_transfer_value', 10, 2)->default(0);
            $table->decimal('ending_value', 10, 2)->default(0);
            $table->decimal('used_value', 10, 2)->default(0);
            $table->decimal('theoretical_usage_value', 10, 2)->default(0);
            $table->decimal('variance_value', 10, 2)->default(0);

            $table->timestamps();

            $table->unique(['franchise_store', 'business_date', 'count_period', 'inventory_category'], 'inv_cogs_unique_hot');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('alta_inventory_cogs_hot');
    }
};
