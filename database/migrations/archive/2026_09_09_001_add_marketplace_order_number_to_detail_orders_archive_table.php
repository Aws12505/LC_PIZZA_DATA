<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'archive';

    public function up(): void
    {
        Schema::connection($this->connection)->table('detail_orders_archive', function (Blueprint $table) {
            $table->string('marketplace_order_number', 50)->nullable()->after('portal_compartments_used');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('detail_orders_archive', function (Blueprint $table) {
            $table->dropColumn('marketplace_order_number');
        });
    }
};
