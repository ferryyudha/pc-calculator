<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpus', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('brand', ['AMD', 'Intel']);
            $table->string('socket', 20);
            // ★ ram_type — required for compatibility validation (Blueprint v2.0)
            $table->enum('ram_type', ['DDR4', 'DDR5', 'DDR4/DDR5']);
            $table->tinyInteger('cores')->unsigned();
            $table->tinyInteger('threads')->unsigned();
            $table->decimal('base_clock', 4, 2);
            $table->decimal('boost_clock', 4, 2);
            $table->smallInteger('tdp')->unsigned(); // Watts — used by PSU Calculator
            $table->integer('price')->unsigned();    // In Rupiah
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpus');
    }
};
