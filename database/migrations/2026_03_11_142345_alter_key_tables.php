<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('key_store_rules', function (Blueprint $table) {
            $table->enum('fill_mode', ['store_once', 'role_each'])->default('store_once');

            // required only if role_each
            $table->string('role_name')->nullable()->index();
        });
        Schema::table('entered_key_values', function (Blueprint $table) {

            $table->unsignedBigInteger('user_id')->nullable()->after('store_id');

            $table->text('note')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('key_store_rules', function (Blueprint $table) {
            $table->dropColumn(['fill_mode', 'role_name']);
        });
        Schema::table('entered_key_values', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'note']);
        });
    }
};
