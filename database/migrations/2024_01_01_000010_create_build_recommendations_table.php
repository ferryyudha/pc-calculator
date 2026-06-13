<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('build_recommendations', function (Blueprint $table) {
            $table->id();
            $table->integer('budget')->unsigned();
            $table->foreignId('game_id')->constrained('games');
            $table->enum('resolution', ['720p', '1080p', '1440p', '4K']);
            $table->foreignId('cpu_id')->constrained('cpus');
            $table->foreignId('gpu_id')->constrained('gpus');
            $table->foreignId('motherboard_id')->constrained('motherboards');
            $table->foreignId('ram_id')->constrained('rams');
            // ssd_id is optional — user may not select SSD
            $table->foreignId('ssd_id')->nullable()->constrained('ssds');
            $table->foreignId('psu_id')->constrained('psus');
            $table->integer('total_price')->unsigned();
            $table->smallInteger('estimated_fps')->unsigned()->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('build_recommendations');
    }
};
