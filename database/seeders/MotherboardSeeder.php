<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Motherboard;
use Illuminate\Support\Facades\Schema;

class MotherboardSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Motherboard::truncate();
        Schema::enableForeignKeyConstraints();

        $csvPath = base_path('motherboard.csv');
        $hasCsv = file_exists($csvPath);
        if (!$hasCsv) {
            $this->command->warn("File motherboard.csv tidak ditemukan — hanya fail-safe Motherboard yang akan diinsert.");
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
        $header = array_map('trim', $header);
        $headerMap = array_flip($header);

        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            // row: [id, category, name]
            if (count($row) < 3) continue;

            $id      = $row[0];
            $category = $row[1];
            $rawName = $row[2];

            // 1. Filter out non-motherboard accessories / cards / bundles / embedded cpu boards
            if (
                str_contains(strtolower($rawName), 'bracket') ||
                str_contains(strtolower($rawName), 'bridge') ||
                str_contains(strtolower($rawName), 'cable') ||
                str_contains(strtolower($rawName), 'antenna') ||
                str_contains(strtolower($rawName), 'wifi adapter') ||
                preg_match('/\bpromo\b/i', $rawName) ||          // exact word: avoid "Promontory"
                str_contains(strtolower($rawName), 'bundle') ||
                str_contains(strtolower($rawName), 'processor') ||
                str_contains(strtolower($rawName), 'prosesor')
            ) {
                $skipped++;
                continue;
            }

            // 2. Extract Socket
            $socket = null;
            if (preg_match('/(LGA\s*\d{3,4}(?:V\d)?(?:-v\d)?)/i', $rawName, $m)) {
                $socket = str_replace(' ', '', strtoupper($m[1]));
            } elseif (preg_match('/(AM\d[+]*)/i', $rawName, $m)) {
                $socket = strtoupper($m[1]);
            } elseif (preg_match('/(FM\d[+]*)/i', $rawName, $m)) {
                $socket = strtoupper($m[1]);
            } elseif (preg_match('/(sTR\w+)/i', $rawName, $m)) {
                $socket = strtoupper($m[1]);
            } elseif (preg_match('/(SP\d+)/i', $rawName, $m)) {
                $socket = strtoupper($m[1]);
            }

            // If no socket found, skip it
            if (!$socket) {
                $skipped++;
                continue;
            }

            // Normalize socket to match cpus table
            $socket = str_replace('-', '', $socket);
            if ($socket === 'LGA2011V3') {
                $socket = 'LGA2011-V3';
            }

            // 3. Clean Name — ambil teks sebelum tanda kurung pertama yang berisi specs
            //    Contoh: "Colorful C.H81-DV plus V20 (LGA1150, Intel H81, DDR3, USB 3.0)"
            //         → "Colorful C.H81-DV plus V20"
            //    Contoh: "MAXSUN (By SOYO) MS-A320M Challenger Series (AM4, A320, DDR4...)"
            //         → "MAXSUN (By SOYO) MS-A320M Challenger Series"
            // Hapus semua trailing parenthesis groups dari belakang
            $cleanName = $rawName;
            while (preg_match('/\s*\([^)]+\)\s*$/', $cleanName)) {
                $cleanName = preg_replace('/\s*\([^)]+\)\s*$/', '', $cleanName);
            }
            $cleanName = trim($cleanName);
            $cleanName = substr($cleanName, 0, 100);

            // 4. Extract Brand
            $brand = 'Other';
            $firstWord = explode(' ', $cleanName)[0];
            if (in_array(strtolower($firstWord), ['asrock', 'asus', 'gigabyte', 'msi', 'biostar', 'colorful', 'evga', 'ecs'])) {
                $brand = $firstWord;
            } else {
                // Heuristic search in name
                if (stripos($rawName, 'asrock') !== false) $brand = 'ASRock';
                elseif (stripos($rawName, 'asus') !== false) $brand = 'ASUS';
                elseif (stripos($rawName, 'gigabyte') !== false) $brand = 'Gigabyte';
                elseif (stripos($rawName, 'msi') !== false) $brand = 'MSI';
                elseif (stripos($rawName, 'biostar') !== false) $brand = 'Biostar';
                elseif (stripos($rawName, 'colorful') !== false) $brand = 'Colorful';
                elseif (stripos($rawName, 'evga') !== false) $brand = 'EVGA';
            }

            // 5. Extract Chipset
            $chipset = 'Other';
            if (preg_match('/\b([ABHZXQ]\d{2,3}|C\d{3})\b/i', $rawName, $m)) {
                $chipset = strtoupper($m[1]);
            }

            // 6. Map RAM Type (Only DDR4/DDR5 supported in schema)
            $ramType = 'DDR4';
            if (stripos($rawName, 'DDR5') !== false || $socket === 'AM5' || $socket === 'LGA1851' || $socket === 'STR5' || $socket === 'SP5') {
                $ramType = 'DDR5';
            }

            // 7. Max RAM
            $maxRam = ($ramType === 'DDR5') ? 192 : 128;

            // 8. Assign Price
            $price = null;
            if (isset($headerMap['price']) && isset($row[$headerMap['price']])) {
                $val = trim($row[$headerMap['price']]);
                $val = str_replace(['Rp', 'rp', ' '], '', $val);

                if (str_contains($val, '.')) {
                    if (substr_count($val, '.') > 1) {
                        $clean = str_replace('.', '', $val);
                        $parsed = (float)$clean;
                        if ($parsed > 50000000) {
                            $parsed = $parsed / 10000;
                        }
                        $price = (int)$parsed;
                    } else {
                        $price = (int)floatval($val);
                    }
                } else {
                    if (str_contains($val, ',')) {
                        $val = str_replace(',', '', $val);
                    }
                    $price = (int)$val;
                }
            }

            if ($price === null || $price === 0) {
                $price = 1500000;
                $chipsetLetter = substr($chipset, 0, 1);
                if (in_array(strtoupper($chipset), ['X99', 'X299', 'TRX40', 'WRX80']) || in_array(strtoupper($chipsetLetter), ['Z', 'X'])) {
                    $price = rand(2800000, 5500000);
                } elseif (in_array(strtoupper($chipsetLetter), ['B', 'H'])) {
                    if (str_contains(strtolower($chipset), 'h110') || str_contains(strtolower($chipset), 'h81') || str_contains(strtolower($chipset), 'h61')) {
                        $price = rand(700000, 1100000);
                    } else {
                        $price = rand(1300000, 2400000);
                    }
                } else {
                    $price = rand(800000, 1400000);
                }
                $price = (int) (round($price / 10000) * 10000);
            }

            Motherboard::create([
                'name' => $cleanName,
                'brand' => $brand,
                'socket' => $socket,
                'chipset' => $chipset,
                'ram_type' => $ramType,
                'max_ram' => $maxRam,
                'price' => $price,
            ]);

            $count++;
        }

        fclose($file);
        } // end if ($hasCsv)

        $this->command->info("Selesai mengimpor {$count} Motherboard dari motherboard.csv (Melewati {$skipped} non-Motherboard/aksesoris).");
    }
}
