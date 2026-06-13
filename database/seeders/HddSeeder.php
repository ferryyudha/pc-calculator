<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Hdd;

class HddSeeder extends Seeder
{
    public function run(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        Hdd::truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $hdds = [
            ['name' => 'WD Blue 1TB 7200RPM',  'capacity' => 1000, 'power_draw' => 6.8, 'price' => 650000],
            ['name' => 'Seagate Barracuda 2TB', 'capacity' => 2000, 'power_draw' => 8.0, 'price' => 850000],
        ];

        foreach ($hdds as $hdd) {
            Hdd::create($hdd);
        }
    }
}
