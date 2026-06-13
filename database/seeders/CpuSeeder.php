<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cpu;
use Illuminate\Support\Facades\Schema;

class CpuSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Cpu::truncate();
        Schema::enableForeignKeyConstraints();

        $csvPath = base_path('processor.csv');
        if (!file_exists($csvPath)) {
            $this->command->error("File processor.csv tidak ditemukan di " . $csvPath);
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
        $header = array_map('trim', $header);
        $headerMap = array_flip($header);

        $count = 0;
        $skipped = 0;
        $uniqueCpus = [];

        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            // row: [id, category, name]
            if (count($row) < 3) continue;

            $id = $row[0];
            $category = $row[1];
            $rawName = $row[2];

            // 1. Filter out non-processors
            if (
                str_contains(strtolower($rawName), 'thermal solution') ||
                str_contains(strtolower($rawName), 'raid upgrade key') ||
                str_contains(strtolower($rawName), 'bracket') ||
                str_contains(strtolower($rawName), 'pasta') ||
                str_contains(strtolower($rawName), 'grease') ||
                str_contains(strtolower($rawName), 'promo') ||
                str_contains(strtolower($rawName), 'bundle')
            ) {
                $skipped++;
                continue;
            }

            // 2. Determine Brand
            $brand = 'Intel';
            if (stripos($rawName, 'amd') !== false) {
                $brand = 'AMD';
            } elseif (stripos($rawName, 'intel') !== false) {
                $brand = 'Intel';
            } else {
                $skipped++;
                continue;
            }

            // 3. Extract Socket
            $socket = null;
            if (preg_match('/(?:Socket\s+)?(LGA\s*\d{3,4}(?:V\d)?(?:-v\d)?)/i', $rawName, $m)) {
                $socket = str_replace(' ', '', strtoupper($m[1]));
            } elseif (preg_match('/(?:Socket\s+)?(AM\d[+]*)/i', $rawName, $m)) {
                $socket = strtoupper($m[1]);
            } elseif (preg_match('/(?:Socket\s+)?(FM\d[+]*)/i', $rawName, $m)) {
                $socket = strtoupper($m[1]);
            } elseif (preg_match('/(?:Socket\s+)?(sTR\w+)/i', $rawName, $m)) {
                $socket = strtoupper($m[1]);
            } elseif (preg_match('/(?:Socket\s+)?(SP\d+)/i', $rawName, $m)) {
                $socket = strtoupper($m[1]);
            } elseif (preg_match('/\b(LGA\d{3,4})\b/i', $rawName, $m)) {
                $socket = strtoupper($m[1]);
            } elseif (preg_match('/\b(AM\d[+]*)\b/i', $rawName, $m)) {
                $socket = strtoupper($m[1]);
            }

            // Default socket fallback if not found
            if (!$socket) {
                if ($brand === 'AMD') {
                    $socket = 'AM4';
                } else {
                    $socket = 'LGA1200';
                }
            }

            // 4. Extract Clocks
            $baseClock = null;
            $boostClock = null;
            if (isset($headerMap['base_clock']) && isset($row[$headerMap['base_clock']]) && is_numeric($row[$headerMap['base_clock']])) {
                $baseClock = (float)$row[$headerMap['base_clock']];
            }
            if (isset($headerMap['boost_clock']) && isset($row[$headerMap['boost_clock']]) && is_numeric($row[$headerMap['boost_clock']])) {
                $boostClock = (float)$row[$headerMap['boost_clock']];
            }

            if ($baseClock === null || $boostClock === null) {
                $baseClock = 3.0;
                $boostClock = 3.8;
                if (preg_match_all('/(\d+\.\d+|\d)\s*G[Hh]z/i', $rawName, $matches)) {
                    if (count($matches[1]) >= 2) {
                        $baseClock = (float) $matches[1][0];
                        $boostClock = (float) $matches[1][1];
                    } elseif (count($matches[1]) == 1) {
                        $baseClock = (float) $matches[1][0];
                        $boostClock = $baseClock + 0.5;
                    }
                }
            }

            // 5. Extract Cores & Threads
            $cores = null;
            $threads = null;
            if (isset($headerMap['cores']) && isset($row[$headerMap['cores']]) && is_numeric($row[$headerMap['cores']])) {
                $cores = (int)$row[$headerMap['cores']];
            }
            if (isset($headerMap['threads']) && isset($row[$headerMap['threads']]) && is_numeric($row[$headerMap['threads']])) {
                $threads = (int)$row[$headerMap['threads']];
            }

            if ($cores === null || $threads === null) {
                $lowerName = strtolower($rawName);
                if (str_contains($lowerName, '9950x')) {
                    $cores = 16; $threads = 32;
                } elseif (str_contains($lowerName, '9900x')) {
                    $cores = 12; $threads = 24;
                } elseif (str_contains($lowerName, '13900k') || str_contains($lowerName, '13900kf')) {
                    $cores = 24; $threads = 32;
                } elseif (str_contains($lowerName, '14700f')) {
                    $cores = 20; $threads = 28;
                } elseif (str_contains($lowerName, '13700f')) {
                    $cores = 16; $threads = 24;
                } elseif (str_contains($lowerName, '265kf') || str_contains($lowerName, '270k')) {
                    $cores = 20; $threads = 20;
                } elseif (str_contains($lowerName, '245kf')) {
                    $cores = 14; $threads = 14;
                } elseif (str_contains($lowerName, '225f')) {
                    $cores = 10; $threads = 10;
                } elseif (str_contains($lowerName, '14400f')) {
                    $cores = 10; $threads = 16;
                } elseif (str_contains($lowerName, '8400') && !str_contains($lowerName, '8400f')) {
                    $cores = 6; $threads = 6;
                } elseif (str_contains($lowerName, '3200g')) {
                    $cores = 4; $threads = 4;
                } elseif (str_contains($lowerName, 'a6-9500')) {
                    $cores = 2; $threads = 2;
                } else {
                    if (preg_match('/(\d+)\s*Core/i', $rawName, $m)) {
                        $cores = (int) $m[1];
                        $threads = $cores * 2;
                    } else {
                        if (stripos($rawName, 'i9') !== false || stripos($rawName, 'ultra 9') !== false) {
                            $cores = 8; $threads = 16;
                        } elseif (stripos($rawName, 'i7') !== false || stripos($rawName, 'ultra 7') !== false) {
                            $cores = 8; $threads = 16;
                        } elseif (stripos($rawName, 'i5') !== false || stripos($rawName, 'ultra 5') !== false) {
                            $cores = 6; $threads = 12;
                        } elseif (stripos($rawName, 'i3') !== false) {
                            $cores = 4; $threads = 8;
                        } elseif (stripos($rawName, 'Ryzen 9') !== false) {
                            $cores = 12; $threads = 24;
                        } elseif (stripos($rawName, 'Ryzen 7') !== false) {
                            $cores = 8; $threads = 16;
                        } elseif (stripos($rawName, 'Ryzen 5') !== false) {
                            $cores = 6; $threads = 12;
                        } elseif (stripos($rawName, 'Ryzen 3') !== false) {
                            $cores = 4; $threads = 8;
                        } elseif (stripos($rawName, 'Pentium') !== false || stripos($rawName, 'Celeron') !== false || stripos($rawName, 'Dual Core') !== false || stripos($rawName, 'Core 2') !== false) {
                            $cores = 2; $threads = 2;
                        } elseif (stripos($rawName, 'EPYC') !== false || stripos($rawName, 'Threadripper') !== false) {
                            $cores = 16; $threads = 32;
                        }
                    }
                }
            }

            if (
                stripos($rawName, 'Pentium') !== false ||
                stripos($rawName, 'Celeron') !== false ||
                stripos($rawName, 'Core 2') !== false ||
                stripos($rawName, 'Dual Core') !== false
            ) {
                $threads = $cores;
            }

            // 6. Extract TDP
            $tdp = null;
            if (isset($headerMap['tdp']) && isset($row[$headerMap['tdp']]) && is_numeric($row[$headerMap['tdp']])) {
                $tdp = (int)$row[$headerMap['tdp']];
            }

            if ($tdp === null) {
                $tdp = 65;
                if (preg_match('/\b(\d{2,3})\s*W\b/', $rawName, $m)) {
                    $tdp = (int) $m[1];
                } else {
                    if (stripos($rawName, 'i9') !== false || stripos($rawName, 'ultra 9') !== false || stripos($rawName, 'Ryzen 9') !== false || stripos($rawName, 'Threadripper') !== false) {
                        $tdp = 125;
                    } elseif (stripos($rawName, 'i7') !== false || stripos($rawName, 'ultra 7') !== false || stripos($rawName, 'Ryzen 7') !== false) {
                        $tdp = 95;
                    } elseif (stripos($rawName, 'EPYC') !== false) {
                        $tdp = 180;
                    }
                }
            }

            // 7. Map RAM Type
            $ramType = null;
            if (isset($headerMap['ram_type']) && isset($row[$headerMap['ram_type']])) {
                $ramType = trim($row[$headerMap['ram_type']]);
            }

            if (empty($ramType)) {
                $ramType = 'DDR4';
                if ($socket === 'AM5' || $socket === 'LGA1851' || $socket === 'STR5' || $socket === 'SP5') {
                    $ramType = 'DDR5';
                } elseif ($socket === 'LGA1700') {
                    $ramType = 'DDR4/DDR5';
                }
            }

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

                if ($price === 0) {
                    $skipped++;
                    continue;
                }
            }

            if ($price === null) {
                $price = 1500000;
                if (stripos($rawName, 'i9') !== false || stripos($rawName, 'ultra 9') !== false || stripos($rawName, 'Ryzen 9') !== false || stripos($rawName, 'Threadripper') !== false || stripos($rawName, 'EPYC') !== false) {
                    $price = rand(6500000, 15000000);
                } elseif (stripos($rawName, 'i7') !== false || stripos($rawName, 'ultra 7') !== false || stripos($rawName, 'Ryzen 7') !== false) {
                    $price = rand(3500000, 6000000);
                } elseif (stripos($rawName, 'i5') !== false || stripos($rawName, 'ultra 5') !== false || stripos($rawName, 'Ryzen 5') !== false) {
                    $price = rand(1800000, 3200000);
                } elseif (stripos($rawName, 'i3') !== false || stripos($rawName, 'Ryzen 3') !== false) {
                    $price = rand(900000, 1600000);
                } else {
                    $price = rand(300000, 800000);
                }
                $price = (int) (round($price / 10000) * 10000);
            }

            // 9. Clean Name
            $cleanName = $rawName;
            if (preg_match('/^(.*?)\s+(?:\d+(?:\.\d+)?\s*G[Hh]z)/i', $rawName, $m)) {
                $cleanName = trim($m[1], " \t\n\r\0\x0B,");
            } else {
                $cleanName = preg_replace('/\[Box\]|\[Tray\]/i', '', $rawName);
                $cleanName = explode(' - ', $cleanName)[0];
                $cleanName = trim($cleanName, " \t\n\r\0\x0B,");
            }

            // Remove any trailing [Box] or [Tray] tags to get a clean model name
            $cleanName = preg_replace('/\s*\[Box\]|\s*\[Tray\]/i', '', $cleanName);
            $cleanName = trim($cleanName);
            $cleanName = substr($cleanName, 0, 100);

            $item = [
                'name' => $cleanName,
                'brand' => $brand,
                'socket' => $socket,
                'ram_type' => $ramType,
                'cores' => $cores,
                'threads' => $threads,
                'base_clock' => $baseClock,
                'boost_clock' => $boostClock,
                'tdp' => $tdp,
                'price' => $price,
            ];

            if (!isset($uniqueCpus[$cleanName])) {
                $uniqueCpus[$cleanName] = $item;
            } else {
                // Keep the one with the higher price
                if ($price > $uniqueCpus[$cleanName]['price']) {
                    $uniqueCpus[$cleanName] = $item;
                }
            }
        }

        fclose($file);

        // Seed unique CPUs to database
        foreach ($uniqueCpus as $item) {
            Cpu::create($item);
            $count++;
        }

        $this->command->info("Selesai mengimpor {$count} CPU unik dari processor.csv (Melewati {$skipped} non-CPU).");
    }
}
