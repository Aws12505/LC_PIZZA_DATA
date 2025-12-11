<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    protected $connection = 'analytics';

    public function up(): void
    {
        Schema::connection($this->connection)->create('daily_item_summary', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();
            $table->string('item_id', 20)->index();
            $table->string('menu_item_name');
            $table->string('menu_item_account');

            // Sales metrics
            $table->integer('quantity_sold')->default(0);
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('net_sales', 15, 2)->default(0);
            $table->decimal('avg_item_price', 10, 2)->default(0);

            // Channel breakdown
            $table->integer('delivery_quantity')->default(0);
            $table->integer('carryout_quantity')->default(0);

            // Modifications
            $table->integer('modified_quantity')->default(0);
            $table->integer('refunded_quantity')->default(0);

            $table->timestamps();
            $table->primary(['id', 'business_date']);
            $table->unique(['franchise_store', 'business_date', 'item_id'], 'unique_daily_item_summary');
            $table->index(['business_date', 'item_id']);
            $table->index('menu_item_account');
        });

        // Add monthly partitioning
        $this->addMonthlyPartitions('daily_item_summary');
    }

    protected function addMonthlyPartitions($tableName): void
    {
        $startDate = Carbon::create(2020, 1, 1);
        $endDate = Carbon::now()->addMonths(3)->endOfMonth();

        $partitions = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $partName = 'p' . $current->format('Ym');
            $nextMonth = $current->copy()->addMonth();
            $partValue = $nextMonth->year * 100 + $nextMonth->month;

            $partitions[] = "PARTITION {$partName} VALUES LESS THAN ({$partValue})";
            $current->addMonth();
        }

        $partitions[] = "PARTITION p_future VALUES LESS THAN MAXVALUE";

        $partitionSql = "ALTER TABLE {$tableName} 
            PARTITION BY RANGE (YEAR(business_date) * 100 + MONTH(business_date)) (" 
            . implode(", ", $partitions) . ")";

        DB::connection($this->connection)->statement($partitionSql);
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('daily_item_summary');
    }
};
