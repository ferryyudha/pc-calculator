<?php

namespace App\Services;

use App\Models\Cpu;
use App\Models\Gpu;
use App\Models\Motherboard;
use App\Models\Ram;
use App\Models\Psu;
use App\Models\Game;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatAssistantService
{
    const GROQ_MODEL = 'llama-3.3-70b-versatile';
    const MAX_TOOL_ITERATIONS = 6;

    public function __construct(
        private CompatibilityService $compatibilityService,
        private FpsService $fpsService,
        private PsuService $psuService,
        private BuildRecommendationService $buildService,
    ) {}

    public function chat(string $userMessage, array $history = []): array
    {
        $apiKey = env('GROQ_API_KEY') ?: config('services.groq.key');

        $systemPrompt = $this->getSystemPrompt();

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Tambahkan history percakapan (maksimal 6 pesan terakhir)
        foreach (array_slice($history, -6) as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $iterations = 0;
        while ($iterations < self::MAX_TOOL_ITERATIONS) {
            $iterations++;

            $response = $this->callGroq($messages, $apiKey);
            if (!$response) {
                return ['reply' => 'Maaf, terjadi kesalahan. Coba lagi sebentar.', 'error' => true];
            }

            Log::info("ChatAssistant response turn {$iterations}: " . $response);

            // Cari semua blok JSON yang memiliki action
            $jsonBlocks = $this->extractJsonBlocks($response);

            if (empty($jsonBlocks)) {
                // Jika tidak ada JSON valid, anggap ini jawaban final langsung
                return ['reply' => $response, 'error' => false];
            }

            // Cari apakah ada final_answer
            foreach ($jsonBlocks as $block) {
                if (isset($block['action']) && $block['action'] === 'final_answer') {
                    return ['reply' => $block['message'] ?? 'Baik.', 'error' => false];
                }
            }

            // Jalankan semua tool_call yang ditemukan di turn ini
            $toolCallsRun = false;
            $toolResultsSummary = [];

            foreach ($jsonBlocks as $block) {
                $action = $block['action'] ?? '';
                $isToolCall = ($action === 'tool_call');
                $isDirectTool = in_array($action, ['search_cpu', 'search_gpu', 'check_compatibility', 'get_fps_estimate', 'recommend_build', 'search_game']);

                if ($isToolCall || $isDirectTool) {
                    $toolName   = $isToolCall ? ($block['tool'] ?? null) : $action;
                    $toolParams = $block['params'] ?? [];

                    $toolResult = $this->executeTool($toolName, $toolParams);
                    $toolResultsSummary[] = "TOOL_RESULT for {$toolName}: " . json_encode($toolResult);
                    $toolCallsRun = true;
                }
            }

            if ($toolCallsRun) {
                $messages[] = ['role' => 'assistant', 'content' => $response];
                $messages[] = [
                    'role'    => 'user',
                    'content' => implode("\n\n", $toolResultsSummary),
                ];
                continue;
            }

            // Fallback
            return ['reply' => $response, 'error' => false];
        }

        return ['reply' => 'Maaf, terlalu banyak proses. Coba pertanyaan yang lebih spesifik.', 'error' => true];
    }

    private function getSystemPrompt(): string
    {
        return <<<PROMPT
Kamu adalah asisten AI untuk toko komputer "PC Calculator". Kamu membantu
pelanggan memilih komponen PC, mengecek kompatibilitas, estimasi FPS game,
dan kebutuhan PSU. Jawab dalam Bahasa Indonesia, ramah, dan ringkas.

Kamu punya akses ke tools berikut. Untuk memanggil tool, balas HANYA
dengan JSON (tanpa markdown):
{
  "action": "tool_call",
  "tool": "<nama_tool>",
  "params": { ... }
}

Tools yang tersedia:

1. search_cpu - cari CPU berdasarkan kata kunci dan/atau budget maksimal
   params: { "keyword": "string opsional", "max_price": integer opsional }

2. search_gpu - cari GPU berdasarkan kata kunci dan/atau budget maksimal
   params: { "keyword": "string opsional", "max_price": integer opsional }

3. check_compatibility - cek kompatibilitas komponen
   params: { "cpu_id": int, "motherboard_id": int, "ram_id": int, "gpu_id": int, "psu_id": int }

4. get_fps_estimate - estimasi FPS untuk game tertentu
   params: { "cpu_id": int, "gpu_id": int, "game_id": int, "resolution": "720p|1080p|1440p|4K" }

5. recommend_build - rekomendasi rakitan berdasarkan budget
   params: { "budget": integer, "game_id": integer opsional, "resolution": "720p|1080p|1440p|4K" }

6. search_game - cari game berdasarkan nama untuk mendapatkan game_id
   params: { "keyword": "string" }

Setelah mendapat hasil tool (akan dikirim sebagai "TOOL_RESULT"), gunakan
data tersebut untuk menjawab pertanyaan user secara natural. Untuk jawaban
final, balas dengan JSON:
{
  "action": "final_answer",
  "message": "<jawaban dalam Bahasa Indonesia, boleh pakai markdown ringan>"
}

PENTING:
- Jangan asal jawab harga atau spek tanpa memanggil tool dulu jika
  pertanyaan menyangkut data toko (harga, ketersediaan, rekomendasi)
- Kalau pertanyaan umum (misal "apa itu DDR5?"), boleh langsung
  final_answer tanpa tool call
- Jika user tidak menentukan game secara spesifik untuk rekomendasi rakitan, kamu dapat memanggil recommend_build tanpa parameter game_id (sistem akan otomatis menggunakan game populer default). Sampaikan acuan game default tersebut pada respons akhir Anda.
- Selalu format harga sebagai "Rp X.XXX.XXX"
- Jika hasil tool kosong/null, sampaikan dengan sopan bahwa data tidak ditemukan
- STRUKTUR TULISAN:
  1. Selalu tampilkan daftar rekomendasi rakitan PC atau kumpulan komponen dengan format bullet-point (menggunakan tanda *).
  2. Berikan baris baru (newline) untuk setiap komponen. Dilarang keras menuliskan daftar komponen atau spesifikasi dalam bentuk satu paragraf panjang yang menumpuk.
  3. Gunakan cetak tebal (bold markdown **seperti ini**) pada label komponen (misal **Prosesor**, **Kartu Grafis**), nama model komponen, dan harga/total harga agar mudah dibaca.
  4. Berikan jarak baris baru ganda (double newline) di antara paragraf pembuka, daftar komponen, dan bagian penutup/FPS.
PROMPT;
    }

    private function executeTool(string $tool, array $params): mixed
    {
        return match ($tool) {
            'search_cpu' => $this->searchCpu($params),
            'search_gpu' => $this->searchGpu($params),
            'search_game' => $this->searchGame($params),
            'check_compatibility' => $this->runCompatibilityCheck($params),
            'get_fps_estimate' => (isset($params['cpu_id'], $params['gpu_id'], $params['game_id']))
                ? $this->fpsService->estimate(
                    (int) $params['cpu_id'],
                    (int) $params['gpu_id'],
                    (int) $params['game_id'],
                    $params['resolution'] ?? '1080p'
                )
                : ['error' => 'cpu_id, gpu_id, dan game_id wajib diisi untuk estimasi FPS.'],
            'recommend_build' => $this->runRecommendBuild($params),
            default => ['error' => "Unknown tool: {$tool}"],
        };
    }

    private function runCompatibilityCheck(array $params): array
    {
        $cpu  = Cpu::find($params['cpu_id'] ?? null);
        $mobo = Motherboard::find($params['motherboard_id'] ?? null);
        $ram  = Ram::find($params['ram_id'] ?? null);
        $gpu  = Gpu::find($params['gpu_id'] ?? null);
        $psu  = Psu::find($params['psu_id'] ?? null);

        if (!$cpu || !$mobo || !$ram || !$gpu || !$psu) {
            return [
                'compatible' => false,
                'error' => 'Satu atau lebih komponen tidak ditemukan di database. Pastikan menyertakan ID yang valid untuk CPU, Motherboard, RAM, GPU, dan PSU.'
            ];
        }

        return $this->compatibilityService->check($cpu, $mobo, $ram, $gpu, $psu);
    }

    private function runRecommendBuild(array $params): array
    {
        $budget     = (int) ($params['budget'] ?? 0);
        $game       = Game::find($params['game_id'] ?? null);
        $resolution = $params['resolution'] ?? '1080p';

        if (!$game) {
            // Gunakan game acuan populer jika user tidak menyertakan game secara spesifik
            $game = Game::where('name', 'like', '%GTA%')->first()
                 ?: Game::where('weight_class', 'medium')->first()
                 ?: Game::first();
        }

        if (!$game) {
            return ['error' => 'Tidak ada game acuan di database untuk melakukan estimasi build.'];
        }

        $res = $this->buildService->recommend($budget, $game, $resolution);

        if (!$res) {
            return ['error' => 'Tidak dapat menemukan kombinasi build yang cocok untuk budget ini. Coba tingkatkan budget.'];
        }

        // Saring respons agar hemat token
        return [
            'cpu' => [
                'name'  => $res['build']['cpu']['name'] ?? '-',
                'price' => $res['build']['cpu']['price'] ?? 0,
            ],
            'gpu' => [
                'name'  => $res['build']['gpu']['name'] ?? '-',
                'price' => $res['build']['gpu']['price'] ?? 0,
            ],
            'motherboard' => [
                'name'  => $res['build']['motherboard']['name'] ?? '-',
                'price' => $res['build']['motherboard']['price'] ?? 0,
            ],
            'ram' => [
                'name'  => $res['build']['ram']['name'] ?? '-',
                'price' => $res['build']['ram']['price'] ?? 0,
            ],
            'ssd' => [
                'name'  => $res['build']['ssd']['name'] ?? '-',
                'price' => $res['build']['ssd']['price'] ?? 0,
            ],
            'psu' => [
                'name'  => $res['build']['psu']['name'] ?? '-',
                'price' => $res['build']['psu']['price'] ?? 0,
            ],
            'total_price'      => $res['total_price'] ?? 0,
            'remaining_budget' => $res['remaining_budget'] ?? 0,
            'estimated_fps'    => $res['estimated_fps'] ?? null,
        ];
    }

    private function searchCpu(array $params): array
    {
        $query = Cpu::query();
        if (!empty($params['keyword'])) {
            $query->where('name', 'like', '%' . $params['keyword'] . '%');
        }
        if (!empty($params['max_price'])) {
            $query->where('price', '<=', $params['max_price']);
        }
        return $query->orderBy('price', 'desc')->limit(5)
            ->get(['id', 'name', 'socket', 'cores', 'threads', 'tdp', 'price'])
            ->toArray();
    }

    private function searchGpu(array $params): array
    {
        $query = Gpu::query();
        if (!empty($params['keyword'])) {
            $query->where('name', 'like', '%' . $params['keyword'] . '%');
        }
        if (!empty($params['max_price'])) {
            $query->where('price', '<=', $params['max_price']);
        }
        return $query->orderBy('price', 'desc')->limit(5)
            ->get(['id', 'name', 'vram', 'power_draw', 'price'])
            ->toArray();
    }

    private function searchGame(array $params): array
    {
        return Game::where('name', 'like', '%' . ($params['keyword'] ?? '') . '%')
            ->limit(5)
            ->get(['id', 'name', 'weight_class'])
            ->toArray();
    }

    private function callGroq(array $messages, string $apiKey): ?string
    {
        $messages = $this->sanitizeMessages($messages);
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])->timeout(25)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => self::GROQ_MODEL,
                'messages'    => $messages,
                'temperature' => 0.3,
                'max_tokens'  => 1024,
            ]);

            if ($response->status() === 429) {
                Log::info('ChatAssistant: Groq 429 rate limit reached. Falling back to llama-3.1-8b-instant');
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ])->timeout(25)->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'       => 'llama-3.1-8b-instant',
                    'messages'    => $messages,
                    'temperature' => 0.3,
                    'max_tokens'  => 1024,
                ]);
            }

            if ($response->successful()) {
                return trim($response->json('choices.0.message.content') ?? '');
            } else {
                Log::warning('ChatAssistant Groq error. Status: ' . $response->status() . ' Body: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::warning('ChatAssistant Groq exception: ' . $e->getMessage());
        }

        return null;
    }

    private function extractJsonBlocks(string $response): array
    {
        $blocks = [];
        $length = strlen($response);
        $braceCount = 0;
        $startPos = -1;

        for ($i = 0; $i < $length; $i++) {
            if ($response[$i] === '{') {
                if ($braceCount === 0) {
                    $startPos = $i;
                }
                $braceCount++;
            } elseif ($response[$i] === '}') {
                if ($braceCount > 0) {
                    $braceCount--;
                    if ($braceCount === 0 && $startPos !== -1) {
                        $jsonCandidate = substr($response, $startPos, $i - $startPos + 1);

                        // Coba decode langsung
                        $decoded = json_decode($jsonCandidate, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            // Coba bersihkan kontrol karakter
                            $sanitized = preg_replace_callback('/"(.*?)"/s', function ($matches) {
                                return '"' . str_replace(["\n", "\r"], ["\\n", "\\r"], $matches[1]) . '"';
                            }, $jsonCandidate);
                            $decoded = json_decode($sanitized, true);
                        }

                        // Coba regex manual jika decode masih gagal
                        if (json_last_error() !== JSON_ERROR_NONE && str_contains($jsonCandidate, '"final_answer"')) {
                            if (preg_match('/"message"\s*:\s*"(.*)"/s', $jsonCandidate, $matches)) {
                                $msg = rtrim($matches[1], "\n\r\t }\"");
                                $decoded = [
                                    'action' => 'final_answer',
                                    'message' => stripcslashes($msg)
                                ];
                            }
                        }

                        if ($decoded && isset($decoded['action'])) {
                            $blocks[] = $decoded;
                        }
                    }
                }
            }
        }

        return $blocks;
    }

    private function sanitizeMessages(array $messages): array
    {
        $sanitized = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $sanitized[] = $msg;
                continue;
            }

            // Find the last non-system message
            $lastNonSystemKey = null;
            for ($i = count($sanitized) - 1; $i >= 0; $i--) {
                if ($sanitized[$i]['role'] !== 'system') {
                    $lastNonSystemKey = $i;
                    break;
                }
            }

            if ($lastNonSystemKey === null) {
                // First non-system message must be user
                if ($msg['role'] === 'user') {
                    $sanitized[] = $msg;
                }
                continue;
            }

            $lastRole = $sanitized[$lastNonSystemKey]['role'];
            if ($lastRole === $msg['role']) {
                // Merge content of same role consecutive messages
                $sanitized[$lastNonSystemKey]['content'] .= "\n" . $msg['content'];
            } else {
                $sanitized[] = $msg;
            }
        }
        return $sanitized;
    }
}
