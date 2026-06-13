<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psus', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->smallInteger('watt')->unsigned(); // 450, 550, 650, 750, 850
            $table->string('certification', 20);      // e.g. 80+ Bronze, 80+ Gold
            $table->integer('price')->unsigned();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psus');
    }
};
