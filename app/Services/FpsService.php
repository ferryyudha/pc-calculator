<?php

namespace App\Services;

use App\Models\Benchmark;

/**
 * FpsService — Blueprint §3.3 Fallback Logic
 *
 * Priority 1 (Exact):     exact CPU+GPU+Game+Resolution match → return measured data
 * Priority 2 (CPU diff):  same GPU+Game+Resolution, diff CPU → adjust ±5% by TDP ratio
 * Priority 3 (Res diff):  same CPU+GPU+Game, diff resolution → scale using standard factors
 * Priority 4 (No data):   return null (controller returns BENCHMARK_NOT_FOUND)
 *
 * Resolution scaling factors (from blueprint §3.3):
 *   1080p → 1440p : ×0.65
 *   1080p → 4K    : ×0.40
 *   1440p → 1080p : ×1.54 (inverse of 0.65)
 *   4K    → 1080p : ×2.50 (inverse of 0.40)
 */
class FpsService
{
    // Scaling factors: source_resolution → target_resolution
    private const RESOLUTION_FACTORS = [
        '720p'  => ['720p' => 1.0,  '1080p' => 0.74, '1440p' => 0.48, '4K' => 0.30],
        '1080p' => ['720p' => 1.35, '1080p' => 1.0,  '1440p' => 0.65, '4K' => 0.40],
        '1440p' => ['720p' => 2.08, '1080p' => 1.54, '1440p' => 1.0,  '4K' => 0.62],
        '4K'    => ['720p' => 3.33, '1080p' => 2.50, '1440p' => 1.61, '4K' => 1.0],
    ];

    public function estimate(int $cpuId, int $gpuId, int $gameId, string $resolution, ?int $ramId = null): array|null
    {
        // Priority 1 — Exact match: data benchmark langsung tersedia di database
        $exact = Benchmark::where([
            'cpu_id' => $cpuId, 'gpu_id' => $gpuId,
            'game_id' => $gameId, 'resolution' => $resolution,
        ])->first();

        $result = null;

        if ($exact) {
            $result = [
                'fps'    => $this->formatFps($exact),
                'source' => $exact->source,
                // Jika data diinterpolasi, beri catatan transparansi ke user
                'note'   => $exact->is_interpolated ? 'FPS ini adalah estimasi berdasarkan hardware serupa' : null,
            ];
        } else {
            // Priority 2 — GPU sama, CPU berbeda: sesuaikan FPS dengan rasio TDP CPU
            // Asumsi: performa GPU mendominasi, CPU hanya berkontribusi ±20% terhadap FPS
            $sameGpuBench = Benchmark::with('cpu')
                ->where(['gpu_id' => $gpuId, 'game_id' => $gameId, 'resolution' => $resolution])
                ->first();

            if ($sameGpuBench) {
                $targetCpu = \App\Models\Cpu::find($cpuId);
                $sourceCpu = $sameGpuBench->cpu;

                if ($targetCpu && $sourceCpu) {
                    $tdpRatio = $targetCpu->tdp / max($sourceCpu->tdp, 1);
                    // Clamp antara 0.80 dan 1.20 (±20%)
                    // CPU biasanya hanya mempengaruhi FPS 10-20% kecuali game CPU-bound
                    $adjustFactor = max(0.80, min(1.20, $tdpRatio));

                    $result = [
                        'fps'    => $this->scaleFps($sameGpuBench, $adjustFactor),
                        'source' => 'interpolated',
                        'note'   => 'FPS adalah estimasi berdasarkan GPU yang sama dengan CPU berbeda',
                    ];
                }
            }

            if (!$result) {
                // Priority 3 — Resolusi berbeda: skala FPS menggunakan faktor RESOLUTION_FACTORS
                // Contoh: data 1080p ada → kalikan dengan faktor untuk mendapat estimasi 4K
                $altResBench = Benchmark::where([
                    'cpu_id' => $cpuId, 'gpu_id' => $gpuId, 'game_id' => $gameId,
                ])->first();

                if ($altResBench) {
                    $factor = self::RESOLUTION_FACTORS[$altResBench->resolution][$resolution] ?? null;
                    if ($factor) {
                        $result = [
                            'fps'    => $this->scaleFps($altResBench, $factor),
                            'source' => 'interpolated',
                            'note'   => "FPS diskalakan dari {$altResBench->resolution} ke {$resolution}",
                        ];
                    }
                }
            }
        }

        // Setelah result['fps'] didapat, apply RAM modifier jika ada
        if ($ramId && $result && isset($result['fps'])) {
            $ram = \App\Models\Ram::find($ramId);
            if ($ram) {
                $factor = $this->getRamFactor($ram);
                $result['fps'] = [
                    'low'    => max(1, (int) round($result['fps']['low']    * $factor)),
                    'medium' => max(1, (int) round($result['fps']['medium'] * $factor)),
                    'high'   => max(1, (int) round($result['fps']['high']   * $factor)),
                    'ultra'  => max(1, (int) round($result['fps']['ultra']  * $factor)),
                ];
                $result['ram_factor']  = $factor;
                $result['ram_applied'] = true;
            }
        }

        return $result;
    }

    /**
     * Hitung faktor pengali performa berdasarkan spesifikasi RAM.
     */
    public function getRamFactor(\App\Models\Ram $ram): float
    {
        $factor = 1.0;

        // --- Faktor Kapasitas ---
        // 4GB: bottleneck parah di game modern (-18%)
        // 8GB: cukup untuk game lama, sering swap di game baru (-8%)
        // 16GB: sweet spot, tidak ada penalty (baseline)
        // 32GB+: tidak ada bonus signifikan untuk gaming (+1%)
        $factor *= match(true) {
            $ram->capacity <= 4  => 0.82,
            $ram->capacity <= 8  => 0.92,
            $ram->capacity <= 16 => 1.0,
            default              => 1.01,
        };

        // --- Faktor Channel Mode ---
        // Single channel (sticks=1): penalti terutama untuk iGPU dan CPU-bound games
        // Dual channel (sticks=2): bonus kecil vs baseline
        $factor *= match($ram->sticks) {
            1       => 0.93,
            default => 1.03,
        };

        // --- Faktor Kecepatan ---
        // DDR4
        if ($ram->ddr_version === 'DDR4') {
            $factor *= match(true) {
                $ram->speed < 2400  => 0.90,
                $ram->speed < 2666  => 0.93,
                $ram->speed < 3000  => 0.96,
                $ram->speed < 3200  => 0.98,
                $ram->speed < 3600  => 1.0,   // baseline DDR4
                $ram->speed < 4000  => 1.02,
                default             => 1.03,
            };
        }

        // DDR5
        if ($ram->ddr_version === 'DDR5') {
            $factor *= match(true) {
                $ram->speed < 5200  => 0.97,
                $ram->speed < 5600  => 1.0,   // baseline DDR5
                $ram->speed < 6000  => 1.02,
                $ram->speed < 6400  => 1.03,
                $ram->speed < 7000  => 1.04,
                default             => 1.05,
            };
        }

        // Clamp final factor antara 0.70 dan 1.10
        // RAM tidak boleh terlalu mendominasi hasil FPS
        return max(0.70, min(1.10, $factor));
    }

    /**
     * Kembalikan semua preset FPS (low/medium/high/ultra) dari data benchmark exact.
     */
    private function formatFps(Benchmark $bench): array
    {
        return [
            'low'    => $bench->fps_low,
            'medium' => $bench->fps_medium,
            'high'   => $bench->fps_high,
            'ultra'  => $bench->fps_ultra,
        ];
    }

    /**
     * Kalikan semua preset FPS dengan factor (pembulatan ke integer).
     * Digunakan untuk interpolasi berdasarkan TDP ratio atau resolusi scaling.
     */
    private function scaleFps(Benchmark $bench, float $factor): array
    {
        return [
            'low'    => (int) round($bench->fps_low    * $factor),
            'medium' => (int) round($bench->fps_medium * $factor),
            'high'   => (int) round($bench->fps_high   * $factor),
            'ultra'  => (int) round($bench->fps_ultra  * $factor),
        ];
    }
}
