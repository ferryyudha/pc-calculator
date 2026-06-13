<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Game;

/**
 * GameSeeder — Mengisi tabel `games` dengan semua game yang didukung aplikasi.
 *
 * MAINTENANCE NOTE: Jika ingin menambah game baru:
 * 1. Tambah baris baru di array $games di bawah.
 * 2. Pastikan 'slug' unik dan menggunakan huruf kecil + tanda hubung (kebab-case).
 * 3. Sesuaikan 'weight_class': 'light' | 'medium' | 'heavy' | 'extreme'
 *    - light   = game ringan (Valorant, CS2, LoL) → FPS tinggi
 *    - medium  = game menengah (Fortnite, GTA V)
 *    - heavy   = game berat (RDR2, Elden Ring)
 *    - extreme = game sangat berat (Cyberpunk, Black Myth Wukong)
 * 4. Set 'min_vram' sesuai kebutuhan minimum VRAM game (dalam GB).
 * 5. Setelah menambah game, jalankan:
 *      php artisan benchmark:generate-ai --limit=50
 *      php artisan benchmark:interpolate
 */
class GameSeeder extends Seeder
{
    public function run(): void
    {
        // Hapus semua data game lama dan reset auto-increment (truncate)
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        Game::truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        // Daftar semua game yang didukung aplikasi
        // Format kolom: name, slug, cover_image, weight_class, min_vram (GB)
        $games = [
            ['name' => 'Valorant',                 'slug' => 'valorant',              'cover_image' => null, 'weight_class' => 'light',   'min_vram' => 4],
            ['name' => 'CS2',                      'slug' => 'cs2',                   'cover_image' => null, 'weight_class' => 'light',   'min_vram' => 4],
            ['name' => 'GTA V',                    'slug' => 'gta-v',                 'cover_image' => null, 'weight_class' => 'medium',  'min_vram' => 6],
            ['name' => 'Fortnite',                 'slug' => 'fortnite',              'cover_image' => null, 'weight_class' => 'medium',  'min_vram' => 6],
            ['name' => 'Cyberpunk 2077',           'slug' => 'cyberpunk-2077',        'cover_image' => null, 'weight_class' => 'extreme', 'min_vram' => 8],
            ['name' => 'Dota 2',                   'slug' => 'dota-2',                'cover_image' => null, 'weight_class' => 'light',   'min_vram' => 4],
            ['name' => 'League of Legends',        'slug' => 'league-of-legends',     'cover_image' => null, 'weight_class' => 'light',   'min_vram' => 4],
            ['name' => 'Apex Legends',             'slug' => 'apex-legends',          'cover_image' => null, 'weight_class' => 'medium',  'min_vram' => 6],
            ['name' => 'PUBG: Battlegrounds',      'slug' => 'pubg-battlegrounds',    'cover_image' => null, 'weight_class' => 'medium',  'min_vram' => 6],
            ['name' => 'Genshin Impact',           'slug' => 'genshin-impact',        'cover_image' => null, 'weight_class' => 'medium',  'min_vram' => 6],
            ['name' => 'Minecraft',                'slug' => 'minecraft',             'cover_image' => null, 'weight_class' => 'light',   'min_vram' => 4],
            ['name' => 'Black Myth: Wukong',       'slug' => 'black-myth-wukong',     'cover_image' => null, 'weight_class' => 'extreme', 'min_vram' => 8],
            ['name' => 'Red Dead Redemption 2',    'slug' => 'red-dead-redemption-2', 'cover_image' => null, 'weight_class' => 'heavy',   'min_vram' => 6],
            ['name' => 'Elden Ring',               'slug' => 'elden-ring',            'cover_image' => null, 'weight_class' => 'heavy',   'min_vram' => 6],
            ['name' => 'Hogwarts Legacy',          'slug' => 'hogwarts-legacy',       'cover_image' => null, 'weight_class' => 'heavy',   'min_vram' => 8],
        ];

        foreach ($games as $game) {
            Game::create($game);
        }
    }
}
