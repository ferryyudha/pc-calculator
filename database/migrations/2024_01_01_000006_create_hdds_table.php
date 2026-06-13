<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hdds', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->smallInteger('capacity')->unsigned(); // In GB
            // power_draw: average 6-10W — used by PSU Calculator
            $table->decimal('power_draw', 4, 2);
            $table->integer('price')->unsigned();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hdds');
    }
};
