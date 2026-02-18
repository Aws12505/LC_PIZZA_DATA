<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entered_keys', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique(); // ONLY label, unique
            $table->enum('data_type', ['text', 'number', 'decimal', 'boolean', 'json']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active']);
            $table->index(['data_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entered_keys');
    }
};
