<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'operational';

    public function up(): void
    {
        Schema::connection($this->connection)->create('channel_data_hot', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();
            $table->string('category');
            $table->string('sub_category');
            $table->string('order_placed_method');
            $table->string('order_fulfilled_method');
            $table->decimal('amount', 10, 2);

            $table->timestamps();

            $table->index(['franchise_store', 'business_date']);
            $table->index('category');
        });

        // Add unique constraint with prefix lengths
        DB::connection($this->connection)->statement("
            ALTER TABLE channel_data_hot
            ADD UNIQUE INDEX unique_channel_data_hot (
                franchise_store,
                business_date,
                category(100),
                sub_category(100),
                order_placed_method(100),
                order_fulfilled_method(100)
            )
        ");
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('channel_data_hot');
    }
};
