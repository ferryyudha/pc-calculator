<?php

namespace App\Services;

use App\Models\Cpu;
use App\Models\Gpu;
use App\Models\Motherboard;
use App\Models\Ram;
use App\Models\Ssd;
use App\Models\Psu;
use App\Models\Game;
use App\Models\BuildRecommendation;

/**
 * BuildRecommendationService — Blueprint §3.4
 *
 * Algoritma Rekomendasi Rakitan:
 * =============================================
 * Input: budget, game_id, resolution
 *
 * Langkah 1: Saring kandidat GPU berdasarkan game.min_vram dan budget (maks 45%)
 * Langkah 2: Untuk tiap GPU, coba kandidat CPU (maks 25% budget)
 * Langkah 3: Untuk tiap CPU, cari Motherboard yang kompatibel (socket + ram_type)
 * Langkah 4: Cari RAM yang kompatibel (ddr_version sesuai dengan mobo)
 * Langkah 4.5: Ambil SSD termurah dari database
 * Langkah 5: Hitung PSU yang dibutuhkan = (cpu.tdp + gpu.power_draw) × 1.3 + 43 overhead
 * Langkah 6: Periksa apakah total harga masih dalam budget
 * Langkah 7: Ambil estimasi FPS dari data benchmark
 *
 * Mengembalikan kombinasi valid pertama yang ditemukan, diurut dari GPU termahal (build terbaik duluan)
 */
class BuildRecommendationService
{
    private const GPU_BUDGET_RATIO = 0.45;
    private const CPU_BUDGET_RATIO = 0.25;

    public function __construct(
        private FpsService $fpsService,
        private PsuRequirementCalculator $psuCalculator
    ) {
    }

    public function recommend(int $budget, Game $game, string $resolution): array|null
    {
        // Langkah 1: Kandidat GPU — harus memenuhi min_vram game, harga maksimal 45% budget
        $gpuCandidates = Gpu::where('vram', '>=', $game->min_vram)
            ->where('price', '<=', (int) ($budget * self::GPU_BUDGET_RATIO))
            ->orderBy('price', 'desc')
            ->get();

        foreach ($gpuCandidates as $gpu) {
            // Langkah 2: Kandidat CPU — harga maksimal 25% budget
            $cpuCandidates = Cpu::where('price', '<=', (int) ($budget * self::CPU_BUDGET_RATIO))
                ->orderBy('price', 'desc')
                ->get();

            foreach ($cpuCandidates as $cpu) {
                // Langkah 3: Cari Motherboard termurah yang kompatibel
                $mobo = Motherboard::where('socket', $cpu->socket)
                    ->where(function ($q) use ($cpu) {
                        // Tangani CPU DDR4/DDR5 — cocokkan dengan salah satu tipe mobo
                        if ($cpu->ram_type === 'DDR4/DDR5') {
                            $q->whereIn('ram_type', ['DDR4', 'DDR5']);
                        } else {
                            $q->where('ram_type', $cpu->ram_type);
                        }
                    })
                    ->orderBy('price', 'asc')
                    ->first();

                if (!$mobo)
                    continue;

                // Langkah 4: Cari RAM termurah yang kompatibel
                $ram = Ram::where('ddr_version', $mobo->ram_type)
                    ->orderBy('price', 'asc')
                    ->first();

                if (!$ram)
                    continue;

                // Langkah 4.5 — Ambil SSD termurah dari database
                $ssd = Ssd::orderBy('price', 'asc')->first();
                if (!$ssd)
                    continue;  // skip jika tidak ada SSD di database

                // Langkah 5: Hitung kebutuhan watt PSU
                $psuNeeded = $this->psuCalculator->calculate($cpu, $gpu);
                $psu = Psu::where('watt', '>=', $psuNeeded)
                    ->orderBy('watt', 'asc')
                    ->first();

                if (!$psu)
                    continue;

                // Langkah 6: Pastikan total harga tidak melebihi budget
                $total = $cpu->price + $gpu->price + $mobo->price + $ram->price + $psu->price + $ssd->price;
                if ($total > $budget)
                    continue;

                // Langkah 7: Ambil estimasi FPS dari data benchmark
                $fpsResult = $this->fpsService->estimate($cpu->id, $gpu->id, $game->id, $resolution);

                // Simpan ke tabel history (tidak memblok response jika gagal)
                try {
                    BuildRecommendation::create([
                        'budget' => $budget,
                        'game_id' => $game->id,
                        'resolution' => $resolution,
                        'cpu_id' => $cpu->id,
                        'gpu_id' => $gpu->id,
                        'motherboard_id' => $mobo->id,
                        'ram_id' => $ram->id,
                        'ssd_id' => $ssd->id,
                        'psu_id' => $psu->id,
                        'total_price' => $total,
                        'estimated_fps' => $fpsResult ? $fpsResult['fps']['high'] : null,
                    ]);
                } catch (\Exception) {
                    // Tidak kritis — kegagalan pencatatan history tidak boleh merusak response utama
                }

                return [
                    'build' => [
                        'cpu' => $this->formatComponent($cpu, ['socket', 'ram_type', 'cores', 'threads', 'base_clock', 'boost_clock', 'tdp', 'image_category']),
                        'gpu' => $this->formatComponent($gpu, ['vram', 'memory_type', 'power_draw', 'image_category']),
                        'motherboard' => $this->formatComponent($mobo, ['socket', 'chipset', 'ram_type', 'image_category']),
                        'ram' => $this->formatComponent($ram, ['ddr_version', 'capacity', 'speed', 'image_category']),
                        'ssd' => $this->formatComponent($ssd, ['type', 'capacity', 'power_draw', 'image_category']),
                        'psu' => $this->formatComponent($psu, ['watt', 'certification', 'image_category']),
                    ],
                    'total_price' => $total,
                    'remaining_budget' => $budget - $total,
                    'estimated_fps' => $fpsResult ? $fpsResult['fps'] : null,
                    'fps_source' => $fpsResult ? $fpsResult['source'] : null,
                ];
            }
        }

        return null;
    }

    private function formatComponent($model, array $extraFields): array
    {
        $data = ['id' => $model->id, 'name' => $model->name, 'price' => $model->price];
        foreach ($extraFields as $field) {
            $data[$field] = $model->$field;
        }
        return $data;
    }
}
