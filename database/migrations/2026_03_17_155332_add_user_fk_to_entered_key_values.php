<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('entered_key_values', function (Blueprint $table) {

            // Ensure column type is correct first (important)
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // Add foreign key
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entered_key_values', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
};