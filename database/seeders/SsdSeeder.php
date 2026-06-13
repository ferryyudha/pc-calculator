<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ssd;
use Illuminate\Support\Facades\Schema;

class SsdSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Ssd::truncate();
        Schema::enableForeignKeyConstraints();

        $csvPath = base_path('ssd.csv');
        $hasCsv = file_exists($csvPath);

        if (!$hasCsv) {
            $this->command->warn("File ssd.csv tidak ditemukan — menggunakan fail-safe SSD.");
            $ssds = [
                ['name' => 'Samsung 870 EVO 1TB SATA', 'type' => 'SATA', 'capacity' => 1000, 'power_draw' => 2.5, 'price' => 900000],
                ['name' => 'WD Black SN770 1TB NVMe',  'type' => 'NVMe', 'capacity' => 1000, 'power_draw' => 6.0, 'price' => 1100000],
                ['name' => 'Samsung 970 Evo Plus 2TB',  'type' => 'NVMe', 'capacity' => 2000, 'power_draw' => 7.5, 'price' => 1800000],
            ];

            foreach ($ssds as $ssd) {
                Ssd::create($ssd);
            }
            return;
        }

        $file = fopen($csvPath, 'r');
        $firstLine = fgets($file);
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        rewind($file);

        // Skip header
        fgetcsv($file, 0, $delimiter);

        $count = 0;
        $skipped = 0;

        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            if (count($row) < 5) continue;

            $id = $row[0];
            $category = $row[1];
            $rawName = $row[2];
            $quantity = $row[3];
            $rawPrice = $row[4]; // Format: e.g. 6.200.000.000

            // Skip out of stock or accessories
            if (
                str_contains(strtolower($rawName), 'out of stock') || 
                str_contains(strtolower($rawName), 'heatsink only')
            ) {
                $skipped++;
                continue;
            }

            // Determine SSD Type (NVMe or SATA)
            $type = 'SATA';
            $lowerName = strtolower($rawName);
            if (
                str_contains($lowerName, 'nvme') ||
                str_contains($lowerName, 'pcie') ||
                str_contains($lowerName, 'm.2 pci')
            ) {
                $type = 'NVMe';
            }

            // Extract Capacity (GB)
            $capacity = 512;
            if (preg_match('/(\d+)\s*(?:GB|Gb)/i', $rawName, $m)) {
                $capacity = (int) $m[1];
            } elseif (preg_match('/(\d+)\s*(?:TB|Tb)/i', $rawName, $m)) {
                $capacity = (int) $m[1] * 1000;
            }

            // Estimate Power Draw (TDP)
            $powerDraw = ($type === 'NVMe') ? 6.0 : 2.5;

            // Parse Price: e.g. 6.200.000.000 -> 6200000000 / 10000 = 620000
            $cleanPrice = str_replace('.', '', $rawPrice);
            $price = (int) ($cleanPrice / 10000);

            // Clean Name
            $cleanName = preg_replace('/-\s*Out Of Stock.*/i', '', $rawName);
            $cleanName = preg_replace('/-\s*Grs.*/i', '', $cleanName);
            $cleanName = trim(explode(' - ', $cleanName)[0]);
            $cleanName = substr($cleanName, 0, 100);

            Ssd::create([
                'name' => $cleanName,
                'type' => $type,
                'capacity' => $capacity,
                'power_draw' => $powerDraw,
                'price' => $price,
            ]);

            $count++;
        }

        fclose($file);
        $this->command->info("Selesai mengimpor {$count} SSD dari ssd.csv (Melewati {$skipped} non-SSD/out of stock).");
    }
}
