<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ram;
use Illuminate\Support\Facades\Schema;

class RamSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Ram::truncate();
        Schema::enableForeignKeyConstraints();

        $csvPath = base_path('ram.csv');
        if (!file_exists($csvPath)) {
            $this->command->error("File ram.csv tidak ditemukan di " . $csvPath);
            return;
        }

        $count = 0;
        $skipped = 0;

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
            if (count($row) < 3) {
                $this->command->warn("Skipped (Columns < 3): " . implode(';', $row));
                continue;
            }

            $id = $row[0];
            $category = $row[1];
            $rawName = $row[2];

            // 1. Filter out laptop RAM (SO-DIMM), Mac memory, EOL, promo bundles, and older DDR generations (DDR3/DDR2/DDR1)
            if (
                str_contains(strtolower($rawName), 'so-dimm') ||
                str_contains(strtolower($rawName), 'sodimm') ||
                str_contains(strtolower($rawName), 'so dimm') ||
                str_contains(strtolower($rawName), 'laptop') ||
                str_contains(strtolower($rawName), 'notebook') ||
                str_contains(strtolower($rawName), 'mac memory') ||
                str_contains(strtolower($rawName), 'promo') ||
                str_contains(strtolower($rawName), 'bundle') ||
                str_contains(strtolower($rawName), 'diabaikan') ||
                str_contains(strtolower($rawName), 'eol') ||
                str_contains(strtolower($rawName), 'ddr3') ||
                str_contains(strtolower($rawName), 'ddr2') ||
                str_contains(strtolower($rawName), 'ddr1') ||
                str_contains(strtolower($rawName), 'sdram') ||
                str_contains(strtolower($rawName), 'sdr')
            ) {
                $this->command->warn("Skipped (Filter list): " . $rawName);
                $skipped++;
                continue;
            }

            // 2. Extract DDR version (Only DDR4/DDR5 supported in schema)
            $ddrVersion = 'DDR4';
            if (stripos($rawName, 'DDR5') !== false) {
                $ddrVersion = 'DDR5';
            } elseif (stripos($rawName, 'DDR4') !== false) {
                $ddrVersion = 'DDR4';
            } else {
                $this->command->warn("Skipped (No DDR4/DDR5 found): " . $rawName);
                $skipped++;
                continue;
            }

            // 3. Extract Capacity and Sticks
            $capacity = 8;
            $sticks = 1;

            // Look for pattern like (8GBx2) or (2x8GB) or (2X8GB) or (2 x 8GB)
            if (preg_match('/(?:\((\d+)\s*GB?x(\d+)\)|\((\d+)\s*x\s*(\d+)\s*GB?\))/i', $rawName, $m)) {
                if (!empty($m[1]) && !empty($m[2])) {
                    $sticks = (int) $m[2];
                    $singleCap = (int) $m[1];
                } else {
                    $sticks = (int) $m[3];
                    $singleCap = (int) $m[4];
                }
                $capacity = $sticks * $singleCap;
            } elseif (preg_match('/(\d+)\s*(?:GB|Gb)\bKit/i', $rawName, $m)) {
                $capacity = (int) $m[1];
                $sticks = 2;
            } elseif (preg_match('/(\d+)\s*(?:GB|Gb)\b/i', $rawName, $m)) {
                $capacity = (int) $m[1];
                if (stripos($rawName, 'Dual Channel') !== false) {
                    $sticks = 2;
                } elseif (stripos($rawName, 'Quad Channel') !== false) {
                    $sticks = 4;
                }
            }

            // Limit capacity to reasonable desktop bounds
            if ($capacity < 4 || $capacity > 256) {
                $capacity = 8;
            }

            // 4. Extract Speed
            $speed = ($ddrVersion === 'DDR5') ? 5600 : 3200;
            if (preg_match('/PC-?21000/i', $rawName)) {
                $speed = 2666;
            } elseif (preg_match('/PC-?25600/i', $rawName)) {
                $speed = 3200;
            } elseif (preg_match('/PC-?28800/i', $rawName)) {
                $speed = 3600;
            } elseif (preg_match('/PC-?38400/i', $rawName)) {
                $speed = 4800;
            } elseif (preg_match('/PC-?44800/i', $rawName)) {
                $speed = 5600;
            } elseif (preg_match('/PC-?48000/i', $rawName)) {
                $speed = 6000;
            } elseif (preg_match('/PC-?51200/i', $rawName)) {
                $speed = 6400;
            } elseif (preg_match('/PC-?57600/i', $rawName)) {
                $speed = 7200;
            } elseif (preg_match('/\b(2133|2400|2666|3000|3200|3600|4000|4133|4266|4800|5200|5600|6000|6400|7200|8000)\b/i', $rawName, $m)) {
                $speed = (int) $m[1];
            } elseif (preg_match('/\b(2133|2400|2666|3000|3200|3600|4000|4133|4266|4800|5200|5600|6000|6400|7200|8000)(?![0-9])/i', $rawName, $m)) {
                $speed = (int) $m[1];
            }

            // 5. Assign Price
            $price = 500000;
            if ($ddrVersion === 'DDR5') {
                if ($capacity >= 64) {
                    $price = rand(2800000, 4500000);
                } elseif ($capacity >= 32) {
                    $price = rand(1500000, 2400000);
                } else {
                    $price = rand(850000, 1300000);
                }
            } else {
                if ($capacity >= 64) {
                    $price = rand(1800000, 2600000);
                } elseif ($capacity >= 32) {
                    $price = rand(1100000, 1600000);
                } elseif ($capacity >= 16) {
                    $price = rand(550000, 850000);
                } else {
                    $price = rand(280000, 480000);
                }
            }

            $price = (int) (round($price / 10000) * 10000);

            // 6. Clean Name
            $cleanName = preg_replace('/-\s*Lifetime\s*Warranty/i', '', $rawName);
            $cleanName = trim(explode(' - ', $cleanName)[0]);
            $cleanName = substr($cleanName, 0, 100);

            Ram::create([
                'name' => $cleanName,
                'ddr_version' => $ddrVersion,
                'capacity' => $capacity,
                'speed' => $speed,
                'sticks' => $sticks,
                'price' => $price,
            ]);

            $count++;
        }

        fclose($file);

        $this->command->info("Selesai mengimpor {$count} RAM dari ram.csv (Melewati {$skipped} non-RAM/laptop RAM).");
    }
}
