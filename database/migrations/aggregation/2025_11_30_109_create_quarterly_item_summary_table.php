<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'analytics';

    public function up(): void
    {
        Schema::connection($this->connection)->create('quarterly_item_summary', function (Blueprint $table) {
            $table->id();
            $table->string('franchise_store', 20)->index();
            $table->year('year_num')->index();
            $table->unsignedTinyInteger('quarter_num')->index();
            $table->date('quarter_start_date');
            $table->date('quarter_end_date');
            $table->string('item_id', 20)->index();
            $table->string('menu_item_name');
            $table->string('menu_item_account');

            // Aggregated sales metrics
            $table->integer('quantity_sold')->default(0);
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('net_sales', 15, 2)->default(0);
            $table->decimal('avg_item_price', 10, 2)->default(0);
            $table->decimal('avg_daily_quantity', 10, 2)->default(0);

            // Channel breakdown
            $table->integer('delivery_quantity')->default(0);
            $table->integer('carryout_quantity')->default(0);

            $table->timestamps();

            $table->unique(['franchise_store', 'year_num', 'quarter_num', 'item_id'], 'unique_quarterly_item');
            $table->index(['year_num', 'quarter_num']);
            $table->index('menu_item_account');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('quarterly_item_summary');
    }
};
