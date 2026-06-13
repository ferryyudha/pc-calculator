<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gpus', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('brand', ['NVIDIA', 'AMD', 'Intel']);
            $table->tinyInteger('vram')->unsigned();   // In GB
            // ★ memory_type — informative for user display (GDDR6, GDDR6X, GDDR7)
            $table->string('memory_type', 10);
            $table->smallInteger('power_draw')->unsigned(); // TDP in Watts — used by PSU Calculator
            $table->integer('price')->unsigned();           // In Rupiah
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gpus');
    }
};
