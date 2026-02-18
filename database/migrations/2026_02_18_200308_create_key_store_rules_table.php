<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('key_store_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('key_id')
                ->constrained('entered_keys')
                ->cascadeOnDelete();

            $table->string('store_id'); // string (no store model)

            $table->enum('frequency_type', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->unsignedInteger('interval')->default(1);

            // weekly
            $table->json('week_days')->nullable(); // [1..7] ISO (Mon=1..Sun=7)

            // monthly modes:
            // A) month_day (1..31)
            $table->unsignedTinyInteger('month_day')->nullable();
            // B) nth weekday (week_of_month: 1..4 or -1 for last) + week_day (1..7)
            $table->integer('week_of_month')->nullable();
            $table->unsignedTinyInteger('week_day')->nullable();
            // C) monthly "any day" => both month_day and (week_of_month+week_day) null

            // yearly uses year_month + month_day
            $table->unsignedTinyInteger('year_month')->nullable(); // 1..12

            $table->date('starts_at');
            $table->date('ends_at')->nullable();

            $table->timestamps();

            $table->unique(['key_id', 'store_id']);
            $table->index(['store_id']);
            $table->index(['frequency_type']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_store_rules');
    }
};
