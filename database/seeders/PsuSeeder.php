<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Psu;
use Illuminate\Support\Facades\Schema;

class PsuSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Psu::truncate();
        Schema::enableForeignKeyConstraints();

        $csvPath = base_path('psu.csv');
        if (!file_exists($csvPath)) {
            $this->command->error("File psu.csv tidak ditemukan di " . $csvPath);
            return;
        }

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

        $count   = 0;
        $skipped = 0;
        $uniquePsus = [];

        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            // row: [id, category, name]
            if (count($row) < 3) continue;

            $rawName = $row[2];

            // 1. Filter out promo / bundle
            if (
                preg_match('/\bpromo\b/i', $rawName) ||
                preg_match('/\bbundle\b/i', $rawName)
            ) {
                $skipped++;
                continue;
            }

            // 2. Extract Watt
            $watt = 0;
            if (preg_match('/\b(\d{3,4})\s*W\b/i', $rawName, $m)) {
                $watt = (int) $m[1];
            } elseif (preg_match('/\bPS-(\d{3,4})[A-Z]*\b/i', $rawName, $m)) {
                $watt = (int) $m[1];
            }

            // Skip jika watt tidak masuk akal
            if ($watt < 300 || $watt > 2500) {
                $skipped++;
                continue;
            }

            // 3. Extract Certification
            $certification = '80+ Bronze';
            if (preg_match('/80\+?\s*(PLUS\s*)?(Cybenetics\s*)?(Titanium)/i', $rawName)) {
                $certification = '80+ Titanium';
            } elseif (preg_match('/80\+?\s*(PLUS\s*)?(Cybenetics\s*)?(Platinum)/i', $rawName)) {
                $certification = '80+ Platinum';
            } elseif (preg_match('/80\+?\s*(PLUS\s*)?(Cybenetics\s*)?(Gold)/i', $rawName)) {
                $certification = '80+ Gold';
            } elseif (preg_match('/80\+?\s*(PLUS\s*)?(Cybenetics\s*)?(Silver)/i', $rawName)) {
                $certification = '80+ Silver';
            } elseif (preg_match('/80\+?\s*(PLUS\s*)?(Bronze)/i', $rawName)) {
                $certification = '80+ Bronze';
            } elseif (preg_match('/80\+?\s*(PLUS\s*)?(White)/i', $rawName)) {
                $certification = '80+ White';
            } elseif (preg_match('/\b80\s*%\b/i', $rawName) || preg_match('/Efficiency Up To 80%/i', $rawName)) {
                $certification = '80+';
            }

            // 4. Assign/Estimate Price
            $price = null;
            if (count($row) >= 5 && !empty(trim($row[4]))) {
                $val = trim($row[4]);
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
                // Estimate Price based on watt + certification
                $basePrice = match(true) {
                    $watt >= 2000 => 4500000,
                    $watt >= 1700 => 4000000,
                    $watt >= 1500 => 3500000,
                    $watt >= 1300 => 2800000,
                    $watt >= 1200 => 2500000,
                    $watt >= 1000 => 2000000,
                    $watt >= 850  => 1500000,
                    $watt >= 750  => 1200000,
                    $watt >= 650  => 950000,
                    $watt >= 550  => 750000,
                    default       => 600000,
                };

                $certMultiplier = match($certification) {
                    '80+ Titanium'  => 1.6,
                    '80+ Platinum'  => 1.4,
                    '80+ Gold'      => 1.2,
                    '80+ Silver'    => 1.1,
                    default         => 1.0,
                };

                $price = (int) (round(($basePrice * $certMultiplier) / 10000) * 10000);
            }

            // 5. Clean Name — ambil sebelum " - " pertama, lalu strip trailing labels (Bulk Package dll)
            $cleanName = trim(explode(' - ', $rawName)[0]);
            // Hapus trailing parenthesis labels seperti (BULK PACKAGE)
            while (preg_match('/\s*\(BULK[^)]*\)\s*$/i', $cleanName)) {
                $cleanName = preg_replace('/\s*\(BULK[^)]*\)\s*$/i', '', $cleanName);
            }
            $cleanName = trim($cleanName);
            $cleanName = substr($cleanName, 0, 100);

            $item = [
                'name'          => $cleanName,
                'watt'          => $watt,
                'certification' => $certification,
                'price'         => $price,
            ];

            if (!isset($uniquePsus[$cleanName])) {
                $uniquePsus[$cleanName] = $item;
            } else {
                // Keep the one with the higher price
                if ($price > $uniquePsus[$cleanName]['price']) {
                    $uniquePsus[$cleanName] = $item;
                }
            }
        }

        fclose($file);

        // Seed unique PSUs
        foreach ($uniquePsus as $item) {
            Psu::create($item);
            $count++;
        }

        $this->command->info("Selesai mengimpor {$count} PSU unik dari psu.csv (Melewati {$skipped} non-PSU/aksesoris).");
    }
}
