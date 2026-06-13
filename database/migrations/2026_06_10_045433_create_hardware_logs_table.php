<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hardware_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->float('cpu_temp')->nullable();
            $table->float('cpu_usage')->nullable();
            $table->float('cpu_voltage')->nullable();
            $table->float('cpu_power')->nullable();
            $table->float('gpu_temp')->nullable();
            $table->float('gpu_usage')->nullable();
            $table->float('gpu_power')->nullable();
            $table->float('ram_usage')->nullable();
            $table->float('power_usage')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hardware_logs');
    }
};
