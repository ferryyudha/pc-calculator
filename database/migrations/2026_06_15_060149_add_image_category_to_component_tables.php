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

        // cpus: brand = 'AMD' -> 'cpu-amd', brand = 'Intel' -> 'cpu-intel'
        \DB::table('cpus')->orderBy('id')->chunk(100, function ($cpus) {
            foreach ($cpus as $cpu) {
                $category = stripos($cpu->brand, 'amd') !== false ? 'cpu-amd' : 'cpu-intel';
                \DB::table('cpus')->where('id', $cpu->id)->update(['image_category' => $category]);
            }
        });

        // gpus: brand = 'NVIDIA' -> 'gpu-nvidia', 'AMD' -> 'gpu-amd', 'Intel' -> 'gpu-intel'
        \DB::table('gpus')->orderBy('id')->chunk(100, function ($gpus) {
            foreach ($gpus as $gpu) {
                $brandLower = strtolower($gpu->brand);
                if (str_contains($brandLower, 'nvidia')) {
                    $category = 'gpu-nvidia';
                } elseif (str_contains($brandLower, 'amd')) {
                    $category = 'gpu-amd';
                } else {
                    $category = 'gpu-intel';
                }
                \DB::table('gpus')->where('id', $gpu->id)->update(['image_category' => $category]);
            }
        });

        // motherboards: semua -> 'motherboard'
        \DB::table('motherboards')->update(['image_category' => 'motherboard']);

        // rams: ddr_version = 'DDR4' -> 'ram-ddr4', 'DDR5' -> 'ram-ddr5'
        \DB::table('rams')->orderBy('id')->chunk(100, function ($rams) {
            foreach ($rams as $ram) {
                $ver = strtolower($ram->ddr_version);
                $category = str_contains($ver, 'ddr5') ? 'ram-ddr5' : 'ram-ddr4';
                \DB::table('rams')->where('id', $ram->id)->update(['image_category' => $category]);
            }
        });

        // psus: semua -> 'psu'
        \DB::table('psus')->update(['image_category' => 'psu']);

        // ssds: semua -> 'ssd'
        \DB::table('ssds')->update(['image_category' => 'ssd']);

        // hdds: semua -> 'hdd'
        \DB::table('hdds')->update(['image_category' => 'hdd']);
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
