<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('ddr_version', ['DDR4', 'DDR5']);
            $table->smallInteger('capacity')->unsigned(); // In GB
            $table->smallInteger('speed')->unsigned();    // In MHz, e.g. 3200, 6000
            $table->tinyInteger('sticks')->unsigned()->default(1); // Number of sticks (1 or 2)
            $table->integer('price')->unsigned();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rams');
    }
};
