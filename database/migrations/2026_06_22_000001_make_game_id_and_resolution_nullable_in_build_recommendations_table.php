<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('build_recommendations', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
        });

        Schema::table('build_recommendations', function (Blueprint $table) {
            $table->foreignId('game_id')->nullable()->change()->constrained('games');
            $table->string('resolution')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('build_recommendations', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
        });

        Schema::table('build_recommendations', function (Blueprint $table) {
            // Fill null fields with defaults to avoid errors when changing to non-nullable
            \DB::table('build_recommendations')->whereNull('game_id')->update(['game_id' => 1]);
            \DB::table('build_recommendations')->whereNull('resolution')->update(['resolution' => '1080p']);

            $table->foreignId('game_id')->nullable(false)->change()->constrained('games');
            $table->enum('resolution', ['720p', '1080p', '1440p', '4K'])->nullable(false)->change();
        });
    }
};
