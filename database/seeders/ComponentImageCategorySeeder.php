<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cpu;
use App\Models\Gpu;
use App\Models\Motherboard;
use App\Models\Ram;
use App\Models\Psu;
use App\Models\Ssd;
use App\Models\Hdd;

class ComponentImageCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // cpus: brand = 'AMD' -> 'cpu-amd', brand = 'Intel' -> 'cpu-intel'
        Cpu::query()->chunkById(100, function ($cpus) {
            foreach ($cpus as $cpu) {
                $category = stripos($cpu->brand, 'amd') !== false ? 'cpu-amd' : 'cpu-intel';
                $cpu->update(['image_category' => $category]);
            }
        });

        // gpus: brand = 'NVIDIA' -> 'gpu-nvidia', 'AMD' -> 'gpu-amd', 'Intel' -> 'gpu-intel'
        Gpu::query()->chunkById(100, function ($gpus) {
            foreach ($gpus as $gpu) {
                $brandLower = strtolower($gpu->brand);
                if (str_contains($brandLower, 'nvidia')) {
                    $category = 'gpu-nvidia';
                } elseif (str_contains($brandLower, 'amd')) {
                    $category = 'gpu-amd';
                } else {
                    $category = 'gpu-intel';
                }
                $gpu->update(['image_category' => $category]);
            }
        });

        // motherboards: semua -> 'motherboard'
        Motherboard::query()->update(['image_category' => 'motherboard']);

        // rams: ddr_version = 'DDR4' -> 'ram-ddr4', 'DDR5' -> 'ram-ddr5'
        Ram::query()->chunkById(100, function ($rams) {
            foreach ($rams as $ram) {
                $ver = strtolower($ram->ddr_version);
                $category = str_contains($ver, 'ddr5') ? 'ram-ddr5' : 'ram-ddr4';
                $ram->update(['image_category' => $category]);
            }
        });

        // psus: semua -> 'psu'
        Psu::query()->update(['image_category' => 'psu']);

        // ssds: semua -> 'ssd'
        Ssd::query()->update(['image_category' => 'ssd']);

        // hdds: semua -> 'hdd'
        Hdd::query()->update(['image_category' => 'hdd']);
    }
}
