<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cpu_id')->constrained('cpus')->onDelete('cascade');
            $table->foreignId('gpu_id')->constrained('gpus')->onDelete('cascade');
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            // ★ resolution — critical: FPS differs per resolution
            $table->enum('resolution', ['720p', '1080p', '1440p', '4K']);
            $table->smallInteger('fps_low')->unsigned();
            $table->smallInteger('fps_medium')->unsigned();
            $table->smallInteger('fps_high')->unsigned();
            $table->smallInteger('fps_ultra')->unsigned();
            // ★ source — measured = real data, interpolated = estimated
            $table->enum('source', ['measured', 'interpolated'])->default('measured');
            // ★ is_interpolated — true if data calculated from nearest benchmark
            $table->boolean('is_interpolated')->default(false);
            $table->timestamps();

            // UNIQUE constraint per blueprint v2.0 — prevent duplicate benchmark entries
            $table->unique(['cpu_id', 'gpu_id', 'game_id', 'resolution'], 'benchmarks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmarks');
    }
};
