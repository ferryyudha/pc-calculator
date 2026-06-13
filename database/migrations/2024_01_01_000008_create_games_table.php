<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('cover_image', 255)->nullable();
            // ★ weight_class — for Build Recommendation when benchmark data is incomplete
            // light=Valorant, medium=GTA V, heavy=Fortnite, extreme=Cyberpunk 2077
            $table->enum('weight_class', ['light', 'medium', 'heavy', 'extreme']);
            // ★ min_vram — minimum VRAM recommended in GB
            $table->tinyInteger('min_vram')->unsigned();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
