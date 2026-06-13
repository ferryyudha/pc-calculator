<?php

namespace App\Console\Commands;

use App\Models\Benchmark;
use App\Models\Cpu;
use App\Models\Gpu;
use App\Models\Game;
use Illuminate\Console\Command;

/**
 * BenchmarkInterpolate — Blueprint §5.2 Tier 3 Strategy
 *
 * Scans all CPU × GPU × Game × Resolution combinations yang kosong di tabel benchmarks,
 * lalu mengisinya menggunakan logika interpolasi FPS dari §3.3.
 * Semua data yang diinterpolasi ditandai is_interpolated = true.
 *
 * Cara penggunaan:
 *   php artisan benchmark:interpolate            # Isi semua gap
 *   php artisan benchmark:interpolate --dry-run  # Preview tanpa menyimpan ke database
 *
 * MAINTENANCE NOTE: Jika menambah game atau hardware baru:
 * 1. Jalankan benchmark:generate-ai --limit=50 terlebih dahulu
 * 2. Lalu jalankan benchmark:interpolate untuk mengisi gap yang tidak ter-generate
 *
 * MAINTENANCE NOTE: Faktor resolusi di bawah HARUS sinkron dengan FpsService::RESOLUTION_FACTORS.
 * Jika mengubah satu, ubah keduanya sekaligus.
 */
class BenchmarkInterpolate extends Command
{
    protected $signature   = 'benchmark:interpolate {--dry-run : Show what would be inserted without saving}';
    protected $description = 'Fill missing benchmark entries using interpolation from nearest existing data (Blueprint §3.3)';

    // Faktor skala resolusi — HARUS konsisten dengan FpsService::RESOLUTION_FACTORS
    // Contoh: 1080p → 4K = × 0.40 (FPS di 4K sekitar 40% dari 1080p)
    private const RESOLUTION_FACTORS = [
        '720p'  => ['720p' => 1.0,  '1080p' => 0.74, '1440p' => 0.48, '4K' => 0.30],
        '1080p' => ['720p' => 1.35, '1080p' => 1.0,  '1440p' => 0.65, '4K' => 0.40],
        '1440p' => ['720p' => 2.08, '1080p' => 1.54, '1440p' => 1.0,  '4K' => 0.62],
        '4K'    => ['720p' => 3.33, '1080p' => 2.50, '1440p' => 1.61, '4K' => 1.0],
    ];

    public function handle(): int
    {
        $resolutions = ['720p', '1080p', '1440p', '4K'];
        $cpus        = Cpu::all();
        $gpus        = Gpu::all();
        $games       = Game::all();
        $isDryRun    = $this->option('dry-run');

        $inserted = 0;
        $skipped  = 0;
        $failed   = 0;

        $this->info("Scanning " . ($cpus->count() * $gpus->count() * $games->count() * 4) . " combinations...");

        foreach ($cpus as $cpu) {
            foreach ($gpus as $gpu) {
                foreach ($games as $game) {
                    foreach ($resolutions as $resolution) {
                        // Skip if already exists
                        $exists = Benchmark::where([
                            'cpu_id' => $cpu->id, 'gpu_id' => $gpu->id,
                            'game_id' => $game->id, 'resolution' => $resolution,
                        ])->exists();

                        if ($exists) { $skipped++; continue; }

                        // Try to find a source entry to interpolate from
                        $result = $this->tryInterpolate($cpu, $gpu, $game, $resolution);

                        if (!$result) { $failed++; continue; }

                        if (!$isDryRun) {
                            Benchmark::create([
                                'cpu_id'         => $cpu->id,
                                'gpu_id'         => $gpu->id,
                                'game_id'        => $game->id,
                                'resolution'     => $resolution,
                                'fps_low'        => $result['fps_low'],
                                'fps_medium'     => $result['fps_medium'],
                                'fps_high'       => $result['fps_high'],
                                'fps_ultra'      => $result['fps_ultra'],
                                'source'         => 'interpolated',
                                'is_interpolated'=> true,
                            ]);
                        }

                        $inserted++;
                        $this->line("  ✓ {$cpu->name} + {$gpu->name} + {$game->name} @ {$resolution}");
                    }
                }
            }
        }

        $this->newLine();
        $this->info("Done! Inserted: {$inserted} | Already exists: {$skipped} | No source data: {$failed}");

        if ($isDryRun && $inserted > 0) {
            $this->warn("(Dry run — nothing was saved. Remove --dry-run to apply.)");
        }

        return Command::SUCCESS;
    }

    /**
     * Coba interpolasi dari data benchmark terdekat yang ada.
     * Tiga strategi dengan prioritas berbeda:
     *   A. GPU sama + Game sama + Resolusi sama, CPU berbeda → adjust by TDP ratio
     *   B. CPU+GPU+Game sama, resolusi berbeda               → scale by resolution factor
     *   C. GPU sama + Game sama (resolusi apapun), CPU berbeda → double interpolation
     *
     * MAINTENANCE NOTE: Jika ingin menambah strategi baru (misal strategi D),
     * tambahkan blok percobaan baru sebelum 'return null' di bawah.
     */
    private function tryInterpolate(Cpu $cpu, Gpu $gpu, Game $game, string $resolution): array|null
    {
        // Strategi A: GPU sama, resolusi sama, CPU berbeda → sesuaikan dengan rasio TDP
        // Logika: GPU mendominasi performa, CPU disesuaikan ±20% berdasarkan TDP
        $sameGpuBench = Benchmark::with('cpu')
            ->where(['gpu_id' => $gpu->id, 'game_id' => $game->id, 'resolution' => $resolution])
            ->first();

        if ($sameGpuBench && $sameGpuBench->cpu) {
            $tdpRatio    = $cpu->tdp / max($sameGpuBench->cpu->tdp, 1);
            $factor      = max(0.80, min(1.20, $tdpRatio));
            return $this->scale($sameGpuBench, $factor);
        }

        // Strategi B: CPU+GPU+Game sama, resolusi berbeda → gunakan RESOLUTION_FACTORS
        $altResBench = Benchmark::where([
            'cpu_id' => $cpu->id, 'gpu_id' => $gpu->id, 'game_id' => $game->id,
        ])->first();

        if ($altResBench) {
            $factor = self::RESOLUTION_FACTORS[$altResBench->resolution][$resolution] ?? null;
            if ($factor) return $this->scale($altResBench, $factor);
        }

        // Strategi C: fallback terakhir — GPU+Game sama (resolusi apapun), CPU berbeda
        // Kombinasikan faktor TDP + faktor resolusi dalam satu perkalian
        $anyBench = Benchmark::with('cpu')
            ->where(['gpu_id' => $gpu->id, 'game_id' => $game->id])
            ->first();

        if ($anyBench && $anyBench->cpu) {
            $tdpRatio   = $cpu->tdp / max($anyBench->cpu->tdp, 1);
            $cpuFactor  = max(0.80, min(1.20, $tdpRatio));
            $resFactor  = self::RESOLUTION_FACTORS[$anyBench->resolution][$resolution] ?? 1.0;
            return $this->scale($anyBench, $cpuFactor * $resFactor);
        }

        // Tidak ada data sumber apapun — kombinasi ini tidak bisa diinterpolasi
        return null;
    }

    /**
     * Skalakan semua nilai FPS dengan faktor tertentu.
     * Nilai minimum adalah 1 (tidak boleh 0 karena benchmark harus > 0).
     */
    private function scale(Benchmark $bench, float $factor): array
    {
        return [
            'fps_low'    => max(1, (int) round($bench->fps_low    * $factor)),
            'fps_medium' => max(1, (int) round($bench->fps_medium * $factor)),
            'fps_high'   => max(1, (int) round($bench->fps_high   * $factor)),
            'fps_ultra'  => max(1, (int) round($bench->fps_ultra  * $factor)),
        ];
    }
}
