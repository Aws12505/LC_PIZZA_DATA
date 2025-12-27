<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    protected $connection = 'archive';

    public function up(): void
    {
        Schema::connection($this->connection)->create('summary_sales_archive', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('franchise_store', 20)->index();
            $table->date('business_date')->index();

            $table->decimal('royalty_obligation', 15, 2)->nullable();
            $table->integer('customer_count')->nullable();
            $table->decimal('taxable_amount', 15, 2)->nullable();
            $table->decimal('non_taxable_amount', 15, 2)->nullable();
            $table->decimal('tax_exempt_amount', 15, 2)->nullable();
            $table->decimal('non_royalty_amount', 15, 2)->nullable();
            $table->decimal('refund_amount', 15, 2)->nullable();
            $table->decimal('sales_tax', 15, 2)->nullable();
            $table->decimal('gross_sales', 15, 2)->nullable();
            $table->decimal('occupational_tax', 15, 2)->nullable();
            $table->decimal('delivery_tip', 15, 2)->nullable();
            $table->decimal('delivery_fee', 15, 2)->nullable();
            $table->decimal('delivery_service_fee', 15, 2)->nullable();
            $table->decimal('delivery_small_order_fee', 15, 2)->nullable();
            $table->decimal('modified_order_amount', 15, 2)->nullable();
            $table->decimal('store_tip_amount', 15, 2)->nullable();
            $table->decimal('prepaid_cash_orders', 15, 2)->nullable();
            $table->decimal('prepaid_non_cash_orders', 15, 2)->nullable();
            $table->decimal('prepaid_sales', 15, 2)->nullable();
            $table->decimal('prepaid_delivery_tip', 15, 2)->nullable();
            $table->decimal('prepaid_in_store_tip_amount', 15, 2)->nullable();
            $table->decimal('over_short', 15, 2)->nullable();
            $table->decimal('previous_day_refunds', 15, 2)->nullable();
            $table->string('saf', 50)->nullable();
            $table->string('manager_notes', 50)->nullable();

            $table->timestamps();
            $table->primary(['id', 'business_date']);
            $table->index(['franchise_store', 'business_date']);
            $table->unique(['franchise_store', 'business_date'], 'unique_summary_sales_archive');
        });

        // Add monthly partitioning with compression
        $this->addMonthlyPartitions('summary_sales_archive');
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

        if (config('features.archive_compression_enabled', true)) {
            DB::connection($this->connection)->statement(
                "ALTER TABLE {$tableName} ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8"
            );
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('summary_sales_archive');
    }
};
