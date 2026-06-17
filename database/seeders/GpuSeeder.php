<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gpu;
use Illuminate\Support\Facades\Schema;

class GpuSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Gpu::truncate();
        Schema::enableForeignKeyConstraints();

        $csvPath = base_path('vga.csv');
        $hasCsv = file_exists($csvPath);
        if (!$hasCsv) {
            $this->command->warn("File vga.csv tidak ditemukan — hanya fail-safe GPU yang akan diinsert.");
        }

        $count = 0;
        $skipped = 0;

        if ($hasCsv) {

        $file = fopen($csvPath, 'r');
        // Detect delimiter by reading first line
        $firstLine = fgets($file);
        $delimiter = ',';
        if (str_contains($firstLine, ';')) {
            $delimiter = ';';
        }
        rewind($file);

        // Read header with the correct delimiter
        $header = fgetcsv($file, 0, $delimiter);

        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            // row: [id, category, name]
            if (count($row) < 3) continue;

            $id = $row[0];
            $category = $row[1];
            $rawName = $row[2];

            // 1. Filter out non-VGA items (SLI Bridge, sync cards, display port cables, etc.)
            if (
                str_contains(strtolower($rawName), 'sli bridge') ||
                str_contains(strtolower($rawName), 'sync card') ||
                str_contains(strtolower($rawName), 'sdi output') ||
                str_contains(strtolower($rawName), 'sdi capture') ||
                str_contains(strtolower($rawName), 'sync module') ||
                str_contains(strtolower($rawName), 'cable') ||
                str_contains(strtolower($rawName), 'antenna') ||
                str_contains(strtolower($rawName), 'accessory') ||
                str_contains(strtolower($rawName), 'bracket') ||
                str_contains(strtolower($rawName), 'promo') ||
                str_contains(strtolower($rawName), 'bundle')
            ) {
                $skipped++;
                continue;
            }

            // 2. Determine Brand (NVIDIA, AMD, Intel)
            $brand = 'NVIDIA';
            $lowerName = strtolower($rawName);
            if (
                str_contains($lowerName, 'radeon') ||
                str_contains($lowerName, 'rx ') ||
                str_contains($lowerName, 'amd') ||
                str_contains($lowerName, 'firepro') ||
                (str_contains($lowerName, 'r7') && !preg_match('/g?ddr7/i', $lowerName)) ||
                (str_contains($lowerName, 'r9') && !preg_match('/g?ddr9/i', $lowerName)) ||
                (str_contains($lowerName, 'r5') && !preg_match('/g?ddr5/i', $lowerName)) ||
                str_contains($lowerName, 'hd ') ||
                str_contains($lowerName, 'hd5') ||
                str_contains($lowerName, 'hd6') ||
                str_contains($lowerName, 'hd7')
            ) {
                $brand = 'AMD';
            } elseif (str_contains($lowerName, 'intel') || str_contains($lowerName, 'arc ')) {
                $brand = 'Intel';
            }

            // 3. Extract VRAM (capacity in GB)
            $vram = 8;
            if (preg_match('/(\d+)\s*(?:GB|Gb|G)(?:\s+(?:GDDR|DDR|HBM|DDR5X|DDR5|DDR6|DDR3|DDR4|SDDR3))/i', $rawName, $m)) {
                $vram = (int) $m[1];
            } elseif (preg_match('/(\d+)\s*(?:GB|Gb)\b/i', $rawName, $m)) {
                $vram = (int) $m[1];
            } elseif (preg_match('/\b(\d+)\s*G\b/i', $rawName, $m)) {
                $vram = (int) $m[1];
            }

            if ($vram < 1 || $vram > 48) {
                $vram = 8;
            }

            // 4. Extract Memory Type
            $memoryType = 'GDDR6';
            if (stripos($rawName, 'DDR5X') !== false || stripos($rawName, 'GDDR5X') !== false) {
                $memoryType = 'GDDR5X';
            } elseif (stripos($rawName, 'GDDR6X') !== false) {
                $memoryType = 'GDDR6X';
            } elseif (stripos($rawName, 'GDDR6') !== false || stripos($rawName, 'DDR6') !== false) {
                $memoryType = 'GDDR6';
            } elseif (stripos($rawName, 'GDDR5') !== false || stripos($rawName, 'DDR5') !== false) {
                $memoryType = 'GDDR5';
            } elseif (stripos($rawName, 'GDDR3') !== false || stripos($rawName, 'DDR3') !== false || stripos($rawName, 'SDDR3') !== false) {
                $memoryType = 'GDDR3';
            } elseif (stripos($rawName, 'HBM') !== false) {
                $memoryType = 'HBM';
            }

            // 5. Estimate Power Draw (TDP) & Min Recommended PSU
            $powerDraw = 150;
            $minPsu = 500;

            if (stripos($rawName, '5090') !== false) {
                $powerDraw = 600;
                $minPsu = 1000;
            } elseif (stripos($rawName, '5080') !== false) {
                $powerDraw = 360;
                $minPsu = 800;
            } elseif (stripos($rawName, '5070 ti') !== false) {
                $powerDraw = 250;
                $minPsu = 700;
            } elseif (stripos($rawName, '5070') !== false) {
                $powerDraw = 220;
                $minPsu = 650;
            } elseif (stripos($rawName, '5060 ti') !== false) {
                $powerDraw = 180;
                $minPsu = 600;
            } elseif (stripos($rawName, '5060') !== false) {
                $powerDraw = 140;
                $minPsu = 550;
            } elseif (stripos($rawName, '4090') !== false || stripos($rawName, '3090') !== false || stripos($rawName, 'Titan X') !== false || stripos($rawName, 'Titan Black') !== false) {
                $powerDraw = 450;
                $minPsu = 850;
            } elseif (stripos($rawName, '4080') !== false || stripos($rawName, '3080') !== false || stripos($rawName, 'Fury X') !== false || stripos($rawName, '390X') !== false) {
                $powerDraw = 320;
                $minPsu = 750;
            } elseif (stripos($rawName, '4070 Ti') !== false || stripos($rawName, '3070 Ti') !== false) {
                $powerDraw = 285;
                $minPsu = 700;
            } elseif (stripos($rawName, '4070 super') !== false) {
                $powerDraw = 220;
                $minPsu = 650;
            } elseif (stripos($rawName, '4070') !== false || stripos($rawName, '3070') !== false || stripos($rawName, '1080 Ti') !== false || stripos($rawName, '980 Ti') !== false) {
                $powerDraw = 200;
                $minPsu = 650;
            } elseif (stripos($rawName, '3060 Ti') !== false) {
                $powerDraw = 200;
                $minPsu = 600;
            } elseif (stripos($rawName, '4060 Ti') !== false) {
                $powerDraw = 160;
                $minPsu = 550;
            } elseif (stripos($rawName, '4060') !== false || stripos($rawName, '3060') !== false || stripos($rawName, '1070 Ti') !== false || stripos($rawName, '1070') !== false || stripos($rawName, '970') !== false || stripos($rawName, 'RX 470') !== false || stripos($rawName, 'RX 570') !== false) {
                $powerDraw = 150;
                $minPsu = 500;
            } elseif (stripos($rawName, '3050') !== false) {
                if (stripos($rawName, '6gb') !== false) {
                    $powerDraw = 75;
                    $minPsu = 300;
                } else {
                    $powerDraw = 130;
                    $minPsu = 450;
                }
            } elseif (stripos($rawName, '1660') !== false || stripos($rawName, '1060') !== false || stripos($rawName, '960') !== false || stripos($rawName, 'RX 460') !== false || stripos($rawName, 'RX 560') !== false) {
                $powerDraw = 120;
                $minPsu = 450;
            } elseif (stripos($rawName, '1050') !== false || stripos($rawName, '950') !== false || stripos($rawName, '750') !== false || stripos($rawName, '550') !== false) {
                $powerDraw = 75;
                $minPsu = 350;
            } elseif (stripos($rawName, '730') !== false || stripos($rawName, '710') !== false || stripos($rawName, '210') !== false || stripos($rawName, '230') !== false) {
                $powerDraw = 19;
                $minPsu = 300;
            } elseif (stripos($rawName, '6900 xt') !== false) {
                $powerDraw = 300;
                $minPsu = 850;
            } elseif (stripos($rawName, '7800 xt') !== false) {
                $powerDraw = 263;
                $minPsu = 700;
            } elseif (stripos($rawName, '6700 xt') !== false) {
                $powerDraw = 230;
                $minPsu = 650;
            } elseif (stripos($rawName, '6600 xt') !== false) {
                $powerDraw = 160;
                $minPsu = 500;
            } elseif (stripos($rawName, '5600 xt') !== false || stripos($rawName, '5600') !== false) {
                $powerDraw = 150;
                $minPsu = 450;
            } elseif (stripos($rawName, '580') !== false) {
                $powerDraw = 185;
                $minPsu = 500;
            } elseif (stripos($rawName, '9060 xt') !== false) {
                $powerDraw = 190;
                $minPsu = 600;
            } elseif (stripos($rawName, '6500 xt') !== false) {
                $powerDraw = 107;
                $minPsu = 400;
            } elseif (stripos($rawName, '9070 gre') !== false) {
                $powerDraw = 260;
                $minPsu = 700;
            } elseif (stripos($rawName, 'b580') !== false) {
                $powerDraw = 190;
                $minPsu = 600;
            }

            // 6. Assign Price
            $price = 2500000;
            if (stripos($rawName, '4090') !== false || stripos($rawName, '3090') !== false) {
                $price = rand(22000000, 35000000);
            } elseif (stripos($rawName, '4080') !== false || stripos($rawName, '3080') !== false) {
                $price = rand(13000000, 18000000);
            } elseif (stripos($rawName, '4070') !== false || stripos($rawName, '3070') !== false || stripos($rawName, '1080 Ti') !== false) {
                $price = rand(6500000, 9500000);
            } elseif (stripos($rawName, '4060') !== false || stripos($rawName, '3060') !== false || stripos($rawName, '1080') !== false || stripos($rawName, '1070 Ti') !== false) {
                $price = rand(3800000, 5200000);
            } elseif (stripos($rawName, '1660') !== false || stripos($rawName, '1070') !== false || stripos($rawName, '1060') !== false || stripos($rawName, 'RX 580') !== false || stripos($rawName, 'RX 480') !== false) {
                $price = rand(1500000, 2600000);
            } elseif (stripos($rawName, '1050 Ti') !== false || stripos($rawName, '1050') !== false || stripos($rawName, '960') !== false || stripos($rawName, '950') !== false || stripos($rawName, 'RX 570') !== false || stripos($rawName, 'RX 470') !== false) {
                $price = rand(800000, 1400000);
            } elseif (stripos($rawName, '730') !== false || stripos($rawName, '710') !== false || stripos($rawName, '210') !== false) {
                $price = rand(300000, 600000);
            }

            $price = (int) (round($price / 10000) * 10000);

            // 7. Clean Name
            $cleanName = preg_replace('/-\s*Garansi.*/i', '', $rawName);
            $cleanName = preg_replace('/Grs\s*\d.*/i', '', $cleanName);
            $cleanName = trim(explode(' - ', $cleanName)[0]);
            $cleanName = substr($cleanName, 0, 100);

            Gpu::create([
                'name' => $cleanName,
                'brand' => $brand,
                'vram' => $vram,
                'memory_type' => $memoryType,
                'power_draw' => $powerDraw,
                'price' => $price,
                'min_recommended_psu' => $minPsu,
            ]);

            $count++;
        }

        fclose($file);
        } // end if ($hasCsv)

        $this->command->info("Selesai mengimpor {$count} GPU dari vga.csv (Melewati {$skipped} non-GPU/aksesoris).");
    }
}
