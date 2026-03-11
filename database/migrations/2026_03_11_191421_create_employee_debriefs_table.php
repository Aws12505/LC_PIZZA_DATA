<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('employee_debriefs', function (Blueprint $table) {

            $table->id();

            $table->string('store_id')->index();

            $table->unsignedBigInteger('user_id')->index();

            $table->string('employee_name');

            $table->text('note');

            $table->date('date')->index();

            $table->timestamps();

            $table->index(['store_id', 'employee_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_debriefs');
    }
};