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

    public function recommend(
        int $budget,
        ?Game $game = null,
        ?string $resolution = null,
        ?string $cpuPreference = null,
        ?string $gpuPreference = null,
        ?int $ramCapacity = null,
        ?int $storageCapacity = null
    ): array|null {
        // Query recommendation popularity to rank candidates
        $cpuPopularity = BuildRecommendation::select('cpu_id')
            ->selectRaw('count(*) as count')
            ->groupBy('cpu_id')
            ->pluck('count', 'cpu_id')
            ->toArray();

        $gpuPopularity = BuildRecommendation::select('gpu_id')
            ->selectRaw('count(*) as count')
            ->groupBy('gpu_id')
            ->pluck('count', 'gpu_id')
            ->toArray();

        // Langkah 1: Kandidat GPU
        if ($gpuPreference) {
            $gpuQuery = Gpu::query();
            $searchGpu = trim($gpuPreference);
            $gpuQuery->where(function ($q) use ($searchGpu) {
                $q->where('name', 'like', '%' . $searchGpu . '%')
                  ->orWhere('name', 'like', '%' . str_replace(' ', '', $searchGpu) . '%');
                
                if (preg_match('/rtx\s*(\d+)\s*(ti)?/i', $searchGpu, $matches)) {
                    $num = $matches[1];
                    $ti = !empty($matches[2]) ? ' Ti' : '';
                    $q->orWhere('name', 'like', "%RTX %{$num}%{$ti}%");
                }
                if (preg_match('/rx\s*(\d+)\s*(xt)?/i', $searchGpu, $matches)) {
                    $num = $matches[1];
                    $xt = !empty($matches[2]) ? ' XT' : '';
                    $q->orWhere('name', 'like', "%RX %{$num}%{$xt}%");
                }
            });
            $gpuCandidates = $gpuQuery->orderBy('price', 'desc')->limit(15)->get();

            if ($gpuCandidates->isEmpty()) {
                $gpuQuery = Gpu::query();
                if ($game) {
                    $gpuQuery->where('vram', '>=', $game->min_vram);
                }
                $gpuCandidates = $gpuQuery->where('price', '<=', (int) ($budget * self::GPU_BUDGET_RATIO))
                    ->orderBy('price', 'desc')
                    ->limit(15)
                    ->get();
            }
        } else {
            $gpuQuery = Gpu::query();
            if ($game) {
                $gpuQuery->where('vram', '>=', $game->min_vram);
            }
            $gpuCandidates = $gpuQuery->where('price', '<=', (int) ($budget * self::GPU_BUDGET_RATIO))
                ->orderBy('price', 'desc')
                ->limit(15)
                ->get();
        }

        // Urutkan GPU candidates berdasarkan popularitas (paling sering direkomendasikan)
        $gpuCandidates = $gpuCandidates->sortByDesc(function ($gpu) use ($gpuPopularity) {
            return $gpuPopularity[$gpu->id] ?? 0;
        })->values();

        $hasPreference = !empty($cpuPreference) || !empty($gpuPreference);
        $bestBuild = null;
        $bestTotalPrice = INF;

        // In-memory query caching to avoid thousands of database queries in tight loops
        $moboCache = [];
        $ramCache = [];
        $ssdCache = [];
        $psuCache = [];

        foreach ($gpuCandidates as $gpu) {
            // Langkah 2: Kandidat CPU
            if ($cpuPreference) {
                $cpuQuery = Cpu::query();
                $searchCpu = trim($cpuPreference);
                $cpuQuery->where(function ($q) use ($searchCpu) {
                    $q->where('name', 'like', '%' . $searchCpu . '%')
                      ->orWhere('name', 'like', '%' . str_replace(' ', '', $searchCpu) . '%');
                    
                    if (preg_match('/i([3579])/i', $searchCpu, $matches)) {
                        $num = $matches[1];
                        $q->orWhere('name', 'like', "%Core i{$num}%");
                    }
                    if (preg_match('/ryzen\s*([3579])/i', $searchCpu, $matches)) {
                        $num = $matches[1];
                        $q->orWhere('name', 'like', "%Ryzen {$num}%");
                    }
                });
                $cpuCandidates = $cpuQuery->orderBy('price', 'desc')->limit(15)->get();

                if ($cpuCandidates->isEmpty()) {
                    $cpuCandidates = Cpu::where('price', '<=', (int) ($budget * self::CPU_BUDGET_RATIO))
                        ->orderBy('price', 'desc')
                        ->limit(15)
                        ->get();
                }
            } else {
                $cpuCandidates = Cpu::where('price', '<=', (int) ($budget * self::CPU_BUDGET_RATIO))
                    ->orderBy('price', 'desc')
                    ->limit(15)
                    ->get();
            }

            // Urutkan CPU candidates berdasarkan popularitas
            $cpuCandidates = $cpuCandidates->sortByDesc(function ($cpu) use ($cpuPopularity) {
                return $cpuPopularity[$cpu->id] ?? 0;
            })->values();

            foreach ($cpuCandidates as $cpu) {
                // Langkah 3: Cari Motherboard termurah yang kompatibel (Cached)
                $moboKey = "{$cpu->socket}_{$cpu->ram_type}";
                if (!array_key_exists($moboKey, $moboCache)) {
                    $moboCache[$moboKey] = Motherboard::where('socket', $cpu->socket)
                        ->where(function ($q) use ($cpu) {
                            if ($cpu->ram_type === 'DDR4/DDR5') {
                                $q->whereIn('ram_type', ['DDR4', 'DDR5']);
                            } else {
                                $q->where('ram_type', $cpu->ram_type);
                            }
                        })
                        ->orderBy('price', 'asc')
                        ->first();
                }
                $mobo = $moboCache[$moboKey];

                if (!$mobo)
                    continue;

                // Langkah 4: Cari RAM termurah yang kompatibel (Cached)
                $ramKey = "{$mobo->ram_type}_{$ramCapacity}";
                if (!array_key_exists($ramKey, $ramCache)) {
                    $ramQuery = Ram::where('ddr_version', $mobo->ram_type);
                    if ($ramCapacity > 0) {
                        $ramQuery->where('capacity', '>=', (int) $ramCapacity);
                    }
                    $ramItem = $ramQuery->orderBy('price', 'asc')->first();

                    if (!$ramItem) {
                        $ramItem = Ram::where('ddr_version', $mobo->ram_type)
                            ->orderBy('price', 'asc')
                            ->first();
                    }
                    $ramCache[$ramKey] = $ramItem;
                }
                $ram = $ramCache[$ramKey];

                if (!$ram)
                    continue;

                // Langkah 4.5 — Ambil SSD termurah dari database (Cached)
                $ssdKey = (int) $storageCapacity;
                if (!array_key_exists($ssdKey, $ssdCache)) {
                    $ssdQuery = Ssd::query();
                    if ($storageCapacity > 0) {
                        $ssdQuery->where('capacity', '>=', (int) $storageCapacity);
                    }
                    $ssdItem = $ssdQuery->orderBy('price', 'asc')->first();

                    if (!$ssdItem) {
                        $ssdItem = Ssd::orderBy('price', 'asc')->first();
                    }
                    $ssdCache[$ssdKey] = $ssdItem;
                }
                $ssd = $ssdCache[$ssdKey];

                if (!$ssd)
                    continue;

                // Langkah 5: Hitung kebutuhan watt PSU (Cached)
                $psuNeeded = $this->psuCalculator->calculate($cpu, $gpu);
                $psuKey = (int) $psuNeeded;
                if (!array_key_exists($psuKey, $psuCache)) {
                    $psuCache[$psuKey] = Psu::where('watt', '>=', $psuNeeded)
                        ->orderBy('watt', 'asc')
                        ->first();
                }
                $psu = $psuCache[$psuKey];

                if (!$psu)
                    continue;

                // Langkah 6: Hitung total harga
                $total = $cpu->price + $gpu->price + $mobo->price + $ram->price + $psu->price + $ssd->price;

                if ($total <= $budget) {
                    // Optional upgrades if there is remaining budget
                    $remaining = $budget - $total;
                    if ($remaining > 0) {
                        // 1. Upgrade RAM
                        $betterRam = Ram::where('ddr_version', $mobo->ram_type)
                            ->where('price', '<=', $ram->price + $remaining)
                            ->orderBy('price', 'desc')
                            ->first();
                        if ($betterRam && $betterRam->price > $ram->price) {
                            $remaining -= ($betterRam->price - $ram->price);
                            $total += ($betterRam->price - $ram->price);
                            $ram = $betterRam;
                        }

                        // 2. Upgrade SSD
                        $betterSsd = Ssd::where('price', '<=', $ssd->price + $remaining)
                            ->orderBy('price', 'desc')
                            ->first();
                        if ($betterSsd && $betterSsd->price > $ssd->price) {
                            $remaining -= ($betterSsd->price - $ssd->price);
                            $total += ($betterSsd->price - $ssd->price);
                            $ssd = $betterSsd;
                        }

                        // 3. Upgrade Motherboard
                        $betterMobo = Motherboard::where('socket', $cpu->socket)
                            ->where('ram_type', $mobo->ram_type)
                            ->where('price', '<=', $mobo->price + $remaining)
                            ->orderBy('price', 'desc')
                            ->first();
                        if ($betterMobo && $betterMobo->price > $mobo->price) {
                            $remaining -= ($betterMobo->price - $mobo->price);
                            $total += ($betterMobo->price - $mobo->price);
                            $mobo = $betterMobo;
                        }

                        // 4. Upgrade PSU
                        $betterPsu = Psu::where('watt', '>=', $psuNeeded)
                            ->where('price', '<=', $psu->price + $remaining)
                            ->orderBy('price', 'desc')
                            ->first();
                        if ($betterPsu && $betterPsu->price > $psu->price) {
                            $remaining -= ($betterPsu->price - $psu->price);
                            $total += ($betterPsu->price - $psu->price);
                            $psu = $betterPsu;
                        }
                    }

                    // Match found within budget! Record recommendation and return immediately
                    $fpsResult = ($game && $resolution) ? $this->fpsService->estimate($cpu->id, $gpu->id, $game->id, $resolution) : null;

                    try {
                        BuildRecommendation::create([
                            'budget' => $budget,
                            'game_id' => $game?->id,
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
                        // ignore
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
                } else {
                    // If we have preference, track the cheapest build that exceeds budget
                    if ($hasPreference && $total < $bestTotalPrice) {
                        $bestTotalPrice = $total;
                        $bestBuild = [
                            'cpu' => $cpu,
                            'gpu' => $gpu,
                            'mobo' => $mobo,
                            'ram' => $ram,
                            'ssd' => $ssd,
                            'psu' => $psu,
                            'total' => $total
                        ];
                    }
                }
            }
        }

        // If no build was under budget, but we found one with explicit preferences, return the cheapest one exceeding budget
        if ($hasPreference && $bestBuild) {
            $cpu = $bestBuild['cpu'];
            $gpu = $bestBuild['gpu'];
            $mobo = $bestBuild['mobo'];
            $ram = $bestBuild['ram'];
            $ssd = $bestBuild['ssd'];
            $psu = $bestBuild['psu'];
            $total = $bestBuild['total'];

            $fpsResult = ($game && $resolution) ? $this->fpsService->estimate($cpu->id, $gpu->id, $game->id, $resolution) : null;

            try {
                BuildRecommendation::create([
                    'budget' => $budget,
                    'game_id' => $game?->id,
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
                // ignore
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
