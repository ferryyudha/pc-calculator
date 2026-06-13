<?php

namespace App\Console\Commands;

use App\Models\Benchmark;
use App\Models\Cpu;
use App\Models\Gpu;
use App\Models\Game;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * BenchmarkGenerateAi — Command untuk mengisi tabel benchmarks menggunakan Groq AI.
 *
 * Cara penggunaan:
 *   php artisan benchmark:generate-ai            # Proses 10 kombinasi (default)
 *   php artisan benchmark:generate-ai --limit=50 # Proses 50 kombinasi sekaligus
 *   php artisan benchmark:generate-ai --fresh    # Hapus semua data, lalu generate ulang
 *
 * MAINTENANCE NOTE: Jika ingin mengganti model AI yang digunakan:
 * - Ganti nilai 'model' di method callGroq() (saat ini: llama-3.3-70b-versatile)
 * - Model alternatif di Groq: llama-3.3-70b-versatile, mixtral-8x7b-32768
 * - Model lebih besar = hasil lebih akurat, tapi lebih lambat dan lebih sering rate-limit
 *
 * MAINTENANCE NOTE: Jika ingin menambah resolusi baru (misal 1440p):
 * 1. Tambahkan '1440p' ke array $resolutions di method handle()
 * 2. Perbarui prompt di callGroq() untuk menyertakan resolusi baru
 * 3. Tambahkan blok enforcement scaling untuk resolusi baru
 */
class BenchmarkGenerateAi extends Command
{
    protected $signature = 'benchmark:generate-ai {--limit=10 : The maximum number of combinations to generate} {--fresh : Empty the benchmarks table first}';
    protected $description = 'Generate realistic benchmark FPS data using Groq AI API and insert into database';

    private ?int $remainingRequests = null;

    public function handle(): int
    {
        // Pastikan GROQ_API_KEY ada di .env sebelum melanjutkan
        // Daftar gratis di: https://console.groq.com
        $apiKey = env('GROQ_API_KEY');
        if (!$apiKey) {
            $this->error("GROQ_API_KEY is not defined in your .env file!");
            $this->info("Please add: GROQ_API_KEY=gsk_your_api_key");
            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        
        if ($this->option('fresh')) {
            $this->warn("Truncating the benchmarks table...");
            \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
            Benchmark::truncate();
            \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
            $this->info("Benchmarks table cleared successfully.");
        }

        $cpus = Cpu::all();
        $gpus = Gpu::all();
        $games = Game::all();
        // Resolusi yang akan di-generate per kombinasi CPU+GPU+Game
        $resolutions = ['720p', '1080p', '4K'];

        $this->info("Starting Groq AI Benchmark generator (Limit: {$limit})...");

        // Cari semua kombinasi CPU+GPU yang belum punya data benchmark sama sekali
        $combinations = [];
        foreach ($cpus as $cpu) {
            foreach ($gpus as $gpu) {
                $hasBenchmark = Benchmark::where([
                    'cpu_id' => $cpu->id,
                    'gpu_id' => $gpu->id,
                ])->exists();

                if (!$hasBenchmark) {
                    $combinations[] = ['cpu' => $cpu, 'gpu' => $gpu];
                }
            }
        }

        $totalMissing = count($combinations);
        $this->info("Found {$totalMissing} CPU + GPU combinations with zero benchmark entries.");

        // Jika semua kombinasi sudah terisi, tidak perlu generate
        if ($totalMissing === 0) {
            $this->info("All combinations already have benchmarks! No generation needed.");
            return self::SUCCESS;
        }

        $toProcess = array_slice($combinations, 0, $limit);
        $this->info("Processing the first " . count($toProcess) . " combinations...");

        $bar = $this->output->createProgressBar(count($toProcess));
        $bar->start();

        $generated = 0;
        $failed = [];

        foreach ($toProcess as $combo) {
            $cpu = $combo['cpu'];
            $gpu = $combo['gpu'];

            $success = false;
            $response = null;
            $attempts = 0;
            $maxAttempts = 3;

            while ($attempts < $maxAttempts && !$success) {
                if ($attempts > 0) {
                    $this->newLine();
                    $this->info("Retrying combination {$cpu->name} + {$gpu->name} (Attempt " . ($attempts + 1) . "/{$maxAttempts})...");
                    sleep(6);
                }

                // Panggil Groq API untuk mendapatkan data benchmark
                $response = $this->callGroq($cpu, $gpu, $games, $apiKey);

                if ($response && isset($response['games'])) {
                    $success = true;
                } else {
                    $attempts++;
                }
            }

            if ($success) {
                $this->insertBenchmarks($response, $cpu, $gpu, $games, $resolutions);
                $generated++;
            } else {
                $failed[] = "{$cpu->name} + {$gpu->name}";
                $this->newLine();
                $this->error("Failed to get correct response for {$cpu->name} + {$gpu->name} after {$maxAttempts} attempts.");
            }

            $bar->advance();

            // Delay rate-limit aware
            $sleepTime = 4;
            if ($this->remainingRequests !== null) {
                if ($this->remainingRequests <= 2) {
                    $this->newLine();
                    $this->info("Rate limit remaining <= 2 ({$this->remainingRequests}). Sleeping for 60 seconds...");
                    $sleepTime = 60;
                } else {
                    $sleepTime = 4;
                }
            } else {
                $sleepTime = 4;
            }
            sleep($sleepTime);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully generated benchmark data for {$generated} combinations!");

        if (count($failed) > 0) {
            $this->newLine();
            $this->warn("The following combinations failed after {$maxAttempts} attempts:");
            foreach ($failed as $fCombo) {
                $this->line(" - {$fCombo}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Kirim request ke Groq AI API dan parse hasilnya sebagai JSON.
     */
    private function callGroq(Cpu $cpu, Gpu $gpu, $games, string $apiKey): array|null
    {
        $gamesList = "";
        foreach ($games as $g) {
            $gamesList .= "- {$g->name} (slug: {$g->slug}, weight: {$g->weight_class})\n";
        }

        $cpuSpec = "{$cpu->name} (Brand: {$cpu->brand}, Socket: {$cpu->socket}, Cores: {$cpu->cores}, Threads: {$cpu->threads}, Base Clock: {$cpu->base_clock}GHz, Boost Clock: {$cpu->boost_clock}GHz, TDP: {$cpu->tdp}W)";
        $gpuSpec = "{$gpu->name} (Brand: {$gpu->brand}, VRAM: {$gpu->vram}GB, Memory Type: {$gpu->memory_type}, TDP/Power Draw: {$gpu->power_draw}W)";

        $prompt = <<<PROMPT
You are a PC hardware benchmark expert. Generate realistic average gaming FPS
for the following hardware in Indonesia market context.

CPU: {$cpuSpec}
GPU: {$gpuSpec}

Games to benchmark:
{$gamesList}

STRICT REALISM RULES — follow these exactly:

RESOLUTION SCALING:
- 720p FPS = 130-140% of 1080p FPS
- 1440p FPS = 60-65% of 1080p FPS  
- 4K FPS = 35-42% of 1080p FPS

QUALITY PRESET SCALING (relative to High preset):
- Low   = 145-155% of High FPS
- Medium = 118-125% of High FPS
- High  = 100% (baseline)
- Ultra = 72-80% of High FPS

HARDWARE TIER REFERENCE for 1080p High FPS:
Tier 1 — Very Weak (GT 710, iGPU only):
  Valorant: 25-40 | CS2: 20-35 | GTA V: 8-15 | Cyberpunk: 3-6

Tier 2 — Budget Old (GTX 960, RX 580, GTX 1660 Super):
  Valorant: 140-200 | CS2: 100-150 | GTA V: 55-80 | Cyberpunk: 15-25

Tier 3 — Budget Modern (RTX 3050, RX 5600 XT, Arc B580):
  Valorant: 200-280 | CS2: 160-220 | GTA V: 90-120 | Cyberpunk: 30-45

Tier 4 — Mid-Range (RTX 3060, RX 6600 XT, RTX 5060, RX 9060 XT):
  Valorant: 280-380 | CS2: 220-300 | GTA V: 120-160 | Cyberpunk: 50-70

Tier 5 — High-End (RTX 4070 Super, RX 7800 XT, RTX 5060 Ti 16GB, RX 9070 GRE):
  Valorant: 380-480 | CS2: 300-400 | GTA V: 160-220 | Cyberpunk: 75-110

Tier 6 — Flagship (RTX 5070, RTX 5070 Ti, RTX 5080, RTX 5090):
  Valorant: 480-600 | CS2: 380-500 | GTA V: 220-300 | Cyberpunk: 120-180

GAME WEIGHT REFERENCE (relative to GTA V difficulty):
- light games (Valorant, CS2, Dota 2, LoL, Minecraft): 2.0-2.5x GTA V FPS
- medium games (Fortnite, Apex Legends, Genshin Impact): 0.85-1.0x GTA V FPS
- heavy games (PUBG, RDR2, Elden Ring): 0.5-0.65x GTA V FPS
- extreme games (Cyberpunk 2077, Black Myth Wukong, Hogwarts Legacy): 0.35-0.50x GTA V FPS

IMPORTANT:
- Never return FPS above 500 for any combination
- Never return FPS below 1
- Values must be integers only
- game_slug must exactly match the slug provided in the games list

Return ONLY a valid JSON object, no markdown, no backticks, no explanation:
{
  "games": [
    {
      "game_slug": "valorant",
      "resolutions": {
        "720p":  { "low": 350, "medium": 290, "high": 240, "ultra": 185 },
        "1080p": { "low": 260, "medium": 215, "high": 175, "ultra": 135 },
        "4K":    { "low": 95,  "medium": 78,  "high": 63,  "ultra": 49  }
      }
    }
  ]
}
PROMPT;

        $this->remainingRequests = null;

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(25)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object']
            ]);

            $remainingHeader = $response->header('x-ratelimit-remaining-requests');
            if ($remainingHeader !== null) {
                $this->remainingRequests = (int) $remainingHeader;
            }

            if ($response->successful()) {
                $json = $response->json();
                $content = $json['choices'][0]['message']['content'] ?? null;
                if ($content) {
                    return json_decode($content, true);
                }
            } else {
                $this->newLine();
                $this->warn("  HTTP " . $response->status() . ": " 
                    . ($response->json('error.message') ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            $this->newLine();
            $this->warn("  Exception: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Simpan setiap resolusi ke database
     */
    private function insertBenchmarks(array $response, Cpu $cpu, Gpu $gpu, $games, array $resolutions): void
    {
        foreach ($response['games'] as $gameData) {
            $slug = $gameData['game_slug'];
            $game = Game::where('slug', $slug)->first();
            if (!$game) continue;

            // Validasi dan perbaiki konsistensi skala FPS dari AI (720p >= 1080p >= 4K)
            $fpsData = $gameData['resolutions'] ?? [];
            
            // Preset kualitas grafis yang valid
            $presets = ['low', 'medium', 'high', 'ultra'];

            // Pastikan nilai 1080p tersedia sebagai baseline, fallback ke nilai default jika tidak ada/bukan array
            if (!isset($fpsData['1080p']) || !is_array($fpsData['1080p'])) {
                $fpsData['1080p'] = ['low' => 60, 'medium' => 50, 'high' => 40, 'ultra' => 30];
            }

            // Paksakan urutan preset 1080p: low > medium > high > ultra
            $v1080 = array_values(array_intersect_key($fpsData['1080p'], array_flip($presets)));
            if (count($v1080) === 4) {
                rsort($v1080); // urutkan dari besar ke kecil (descending)
                $fpsData['1080p'] = ['low' => $v1080[0], 'medium' => $v1080[1], 'high' => $v1080[2], 'ultra' => $v1080[3]];
            }

            // Validasi 720p: FPS harus >= 1080p (biasanya sekitar 1.35x lebih tinggi)
            if (!isset($fpsData['720p']) || !is_array($fpsData['720p'])) {
                $fpsData['720p'] = [];
            }
            foreach ($presets as $p) {
                $val1080 = $fpsData['1080p'][$p] ?? 30;
                $val720 = $fpsData['720p'][$p] ?? null;
                if ($val720 === null || $val720 < $val1080) {
                    $fpsData['720p'][$p] = (int) round($val1080 * 1.35);
                }
            }
            // Urutkan 720p dari besar ke kecil
            $v720 = array_values(array_intersect_key($fpsData['720p'], array_flip($presets)));
            rsort($v720);
            $fpsData['720p'] = ['low' => $v720[0], 'medium' => $v720[1], 'high' => $v720[2], 'ultra' => $v720[3]];

            // Validasi 4K: FPS harus <= 1080p (biasanya sekitar 0.40x lebih rendah)
            if (!isset($fpsData['4K']) || !is_array($fpsData['4K'])) {
                $fpsData['4K'] = [];
            }
            foreach ($presets as $p) {
                $val1080 = $fpsData['1080p'][$p] ?? 30;
                $val4K = $fpsData['4K'][$p] ?? null;
                if ($val4K === null || $val4K > $val1080) {
                    $fpsData['4K'][$p] = max(1, (int) round($val1080 * 0.40));
                }
            }
            // Urutkan 4K dari besar ke kecil
            $v4K = array_values(array_intersect_key($fpsData['4K'], array_flip($presets)));
            rsort($v4K);
            $fpsData['4K'] = ['low' => $v4K[0], 'medium' => $v4K[1], 'high' => $v4K[2], 'ultra' => $v4K[3]];

            // Simpan setiap resolusi ke database
            foreach ($resolutions as $res) {
                $resPresets = $fpsData[$res] ?? [];
                Benchmark::updateOrCreate([
                    'cpu_id' => $cpu->id,
                    'gpu_id' => $gpu->id,
                    'game_id' => $game->id,
                    'resolution' => $res,
                ], [
                    'fps_low' => max(1, (int) ($resPresets['low'] ?? 60)),
                    'fps_medium' => max(1, (int) ($resPresets['medium'] ?? 50)),
                    'fps_high' => max(1, (int) ($resPresets['high'] ?? 40)),
                    'fps_ultra' => max(1, (int) ($resPresets['ultra'] ?? 30)),
                    'source' => 'measured',
                    'is_interpolated' => false,
                ]);
            }
        }
    }
}
