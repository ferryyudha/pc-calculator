<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motherboards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('brand', 50);
            $table->string('socket', 20);   // Must match cpu.socket
            $table->string('chipset', 20);
            $table->enum('ram_type', ['DDR4', 'DDR5']); // Must match cpu.ram_type and ram.ddr_version
            $table->smallInteger('max_ram')->unsigned(); // In GB
            $table->integer('price')->unsigned();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motherboards');
    }
};
