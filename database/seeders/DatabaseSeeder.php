<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder — Entry point untuk semua seeder database.
 *
 * MAINTENANCE NOTE: Urutan pemanggilan seeder SANGAT PENTING.
 * - Seeder master data (CPU, GPU, dll.) harus dipanggil SEBELUM BenchmarkSeeder,
 *   karena tabel benchmarks memiliki foreign key ke tabel-tabel tersebut.
 *
 * Cara menjalankan semua seeder dari awal (reset + isi ulang):
 *   php artisan migrate:fresh --seed
 *
 * Cara menjalankan satu seeder spesifik tanpa reset:
 *   php artisan db:seed --class=GpuSeeder
 *
 * JIKA MENAMBAH SEEDER BARU:
 * 1. Buat file Seeder baru di folder database/seeders/
 * 2. Tambahkan class-nya ke array $this->call() di bawah
 * 3. Pastikan posisi pemanggilan tidak melanggar dependensi foreign key
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Order matters: benchmarks depend on cpus, gpus, games being present first.
     */
    public function run(): void
    {
        $this->call([
            // Master data — no dependencies (bisa dijalankan dalam urutan apa pun)
            CpuSeeder::class,
            GpuSeeder::class,
            MotherboardSeeder::class,
            RamSeeder::class,
            SsdSeeder::class,
            HddSeeder::class,
            PsuSeeder::class,
            GameSeeder::class,
            // Benchmark data — harus setelah CPU, GPU, dan Game ada di database
            BenchmarkSeeder::class,
        ]);
    }
}
