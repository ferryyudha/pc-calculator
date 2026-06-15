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
        $tables = ['cpus', 'gpus', 'motherboards', 'rams', 'psus', 'ssds', 'hdds'];
        foreach ($tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('image_category')->nullable()->after('price');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['cpus', 'gpus', 'motherboards', 'rams', 'psus', 'ssds', 'hdds'];
        foreach ($tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropColumn('image_category');
            });
        }
    }
};
