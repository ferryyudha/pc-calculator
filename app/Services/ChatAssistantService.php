<?php

namespace App\Services;

use App\Models\Cpu;
use App\Models\Gpu;
use App\Models\Motherboard;
use App\Models\Ram;
use App\Models\Psu;
use App\Models\Game;
use App\Models\Ssd;
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
    ) {
    }

    public function chat(string $userMessage, array $history = [], array $internalMessages = []): array
    {
        $apiKey = env('GROQ_API_KEY') ?: config('services.groq.key');

        if (!empty($internalMessages)) {
            // Lanjutkan dari sesi sebelumnya (berisi TOOL_RESULT dari turn sebelumnya)
            $messages = $internalMessages;
            // Pastikan system prompt tetap di posisi pertama
            if (($messages[0]['role'] ?? '') !== 'system') {
                array_unshift($messages, ['role' => 'system', 'content' => $this->getSystemPrompt()]);
            }
        } else {
            $systemPrompt = $this->getSystemPrompt();
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
            ];
            // Tambahkan history percakapan (maksimal 6 pesan terakhir)
            foreach (array_slice($history, -6) as $h) {
                $messages[] = ['role' => $h['role'], 'content' => $h['content']];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $iterations = 0;
        while ($iterations < self::MAX_TOOL_ITERATIONS) {
            $iterations++;

            $response = $this->callGroq($messages, $apiKey);
            if (!$response) {
                Log::channel('chat_errors')->error('[ChatAssistant] Groq tidak merespons, iterasi ke-' . $iterations, [
                    'user_message' => $userMessage,
                    'turn' => $iterations,
                ]);
                return ['reply' => 'Maaf, terjadi kesalahan. Coba lagi sebentar.', 'error' => true, 'internal_messages' => $messages];
            }

            Log::info("ChatAssistant response turn {$iterations}: " . $response);

            // Cari semua blok JSON yang memiliki action
            $jsonBlocks = $this->extractJsonBlocks($response);

            if (empty($jsonBlocks)) {
                // Jika tidak ada JSON valid, anggap ini jawaban final langsung
                $messages[] = ['role' => 'assistant', 'content' => $response];
                return ['reply' => $this->cleanReply($response), 'error' => false, 'internal_messages' => $this->stripSystemMessage($messages)];
            }

            // Cari apakah ada final_answer
            foreach ($jsonBlocks as $block) {
                if (isset($block['action']) && $block['action'] === 'final_answer') {
                    $finalReply = $this->cleanReply($block['message'] ?? 'Baik.');
                    $messages[] = ['role' => 'assistant', 'content' => $finalReply];
                    return ['reply' => $finalReply, 'error' => false, 'internal_messages' => $this->stripSystemMessage($messages)];
                }
            }

            // Jalankan semua tool_call yang ditemukan di turn ini
            $toolCallsRun = false;
            $toolResultsSummary = [];

            foreach ($jsonBlocks as $block) {
                $action = $block['action'] ?? '';
                // Beberapa model (mis. llama-4-scout) mengirim "tool_calls" (jamak) bukan "tool_call"
                $isToolCall = in_array($action, ['tool_call', 'tool_calls', 'call_tool']);
                $isDirectTool = in_array($action, ['search_cpu', 'search_gpu', 'search_ram', 'search_psu', 'search_motherboard', 'search_ssd', 'check_compatibility', 'get_fps_estimate', 'recommend_build', 'search_game']);

                if ($isToolCall || $isDirectTool) {
                    $toolName   = $isToolCall ? ($block['tool'] ?? null) : $action;
                    $toolParams = $block['params'] ?? [];

                    $toolResult = $this->executeTool($toolName, $toolParams);

                    $isSingleSearchResult = in_array($toolName, ['search_cpu', 'search_gpu', 'search_ram', 'search_psu', 'search_motherboard', 'search_ssd'])
                        && is_array($toolResult)
                        && count($toolResult) === 1;

                    if ($isSingleSearchResult) {
                        $toolResultsSummary[] = "TOOL_RESULT for {$toolName} (HANYA 1 HASIL — ikuti KASUS B, langsung pilih item ini tanpa menampilkan daftar atau menanyakan konfirmasi nomor): " . json_encode($toolResult);
                    } else {
                        $toolResultsSummary[] = "TOOL_RESULT for {$toolName}: " . json_encode($toolResult);
                    }

                    $toolCallsRun = true;
                }
            }

            if ($toolCallsRun) {
                $messages[] = ['role' => 'assistant', 'content' => $response];
                $messages[] = [
                    'role' => 'user',
                    'content' => implode("\n\n", $toolResultsSummary),
                ];
                continue;
            }

            // JSON ada tapi bukan tool_call / final_answer yang dikenal
            // Minta model untuk membuat final_answer yang proper
            $messages[] = ['role' => 'assistant', 'content' => $response];
            $messages[] = [
                'role' => 'user',
                'content' => 'Tolong berikan jawaban final dalam format JSON: {"action":"final_answer","message":"..."}',
            ];
            continue;
        }

        return ['reply' => 'Maaf, terlalu banyak proses. Coba pertanyaan yang lebih spesifik.', 'error' => true, 'internal_messages' => $this->stripSystemMessage($messages)];
    }

    /**
     * Hapus system message dari array messages sebelum dikirim ke client
     * agar system prompt tidak terekspos dan tidak memenuhi payload.
     */
    private function stripSystemMessage(array $messages): array
    {
        return array_values(array_filter($messages, fn($m) => ($m['role'] ?? '') !== 'system'));
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

3. search_ram - cari RAM berdasarkan kata kunci dan/atau budget maksimal
   params: { "keyword": "string opsional", "max_price": integer opsional }

4. search_psu - cari PSU berdasarkan kata kunci dan/atau budget maksimal
   params: { "keyword": "string opsional", "max_price": integer opsional }

5. search_motherboard - cari Motherboard berdasarkan kata kunci dan/atau budget maksimal
   params: { "keyword": "string opsional", "max_price": integer opsional }

6. search_ssd - cari SSD berdasarkan kata kunci dan/atau budget maksimal
   params: { "keyword": "string opsional", "max_price": integer opsional }

7. check_compatibility - cek kompatibilitas komponen
   params: { "cpu_id": int, "motherboard_id": int, "ram_id": int, "gpu_id": int, "psu_id": int }

8. get_fps_estimate - estimasi FPS untuk game tertentu
   params: { "cpu_id": int, "gpu_id": int, "game_id": int, "resolution": "720p|1080p|1440p|4K" }

9. recommend_build - rekomendasi rakitan berdasarkan budget
   params: { "budget": integer, "game_id": integer opsional, "resolution": "720p|1080p|1440p|4K" }

10. search_game - cari game berdasarkan nama untuk mendapatkan game_id
    params: { "keyword": "string" }

Setelah mendapat hasil tool (akan dikirim sebagai "TOOL_RESULT"), gunakan
data tersebut untuk menjawab pertanyaan user secara natural. Untuk jawaban
final, balas dengan JSON:
{
  "action": "final_answer",
  "message": "<jawaban dalam Bahasa Indonesia, boleh pakai markdown ringan>"
}

PENTING:
- DILARANG KERAS mengarang/merekomendasikan komponen PC, harga, atau spesifikasi secara manual dari ingatan Anda. Anda WAJIB memanggil tool untuk mendapatkan data nyata dari database toko.
- DILARANG KERAS menulis teks status seperti "Mohon tunggu...", "Saya sedang memeriksa...", "Satu saat..." sebelum memanggil tool. Jika ingin memanggil tool, LANGSUNG balas dengan JSON tool call saja, tanpa teks apapun.
- KOMPATIBILITAS: Jika user bertanya apakah komponen kompatibel (contoh: "kompatibel?", "cocok gak?", "bisa dipasang?"):
  1. Ambil id CPU, GPU, Motherboard, RAM, dan PSU dari hasil TOOL_RESULT recommend_build atau search sebelumnya di history percakapan.
  2. Panggil tool check_compatibility dengan id-id tersebut.
  3. DILARANG mengarang jawaban kompatibilitas tanpa memanggil tool.
- GANTI KOMPONEN (KHUSUS): Aturan ini HANYA berlaku jika user secara eksplisit meminta mengganti/mencari komponen alternatif (contoh: "ganti RAM-nya jadi Klev", "prosesornya mau Ryzen 5", "PSU-nya ganti", "RAM-nya stok gak ada mau ganti", "motherboard ganti B550"). TIDAK berlaku untuk rekomendasi rakitan lengkap.
  Jika kondisi ini terpenuhi, gunakan tool search_cpu / search_gpu / search_ram / search_psu / search_motherboard / search_ssd dengan keyword komponen yang diminta, lalu:

  KASUS A — Hasil pencarian LEBIH DARI 1 item:
  1. Tampilkan SEMUA pilihan (maks 5) dalam format bullet-point BERNOMOR (1., 2., 3., dst), lengkap dengan nama dan harga.
  2. Akhiri HANYA dengan kalimat: "Silakan pilih nomor yang Anda inginkan."
  3. WAJIB berhenti di sini. JANGAN memilihkan salah satu komponen atau memperbarui spesifikasi rakitan pada turn yang sama. Tunggu user membalas dengan nomor pilihan.

  KASUS B — Hasil pencarian HANYA 1 item (atau ditandai "HANYA 1 HASIL" pada TOOL_RESULT):
  1. JANGAN tampilkan daftar bernomor. JANGAN tanya "pilih nomor yang Anda inginkan".
  2. Langsung anggap item tersebut sebagai pilihan final user.
  3. Beri jawaban final_answer singkat, contoh: "Ditemukan satu komponen yang cocok: **<nama>** - **Rp <harga>**. Spesifikasi rakitan Anda telah diperbarui." (sesuaikan kalimat dengan konteks, jangan menyalin teks ini secara harfiah).

  KASUS C — Hasil pencarian KOSONG (0 item):
  1. Sampaikan dengan sopan bahwa komponen yang diminta tidak ditemukan di database, dan tawarkan untuk mencari dengan kata kunci lain.
- REKOMENDASI RAKITAN (recommend_build): Saat merespons hasil recommend_build, tampilkan rakitan lengkap sebagai jawaban final. JANGAN tambahkan "Silakan pilih nomor" di akhir karena ini bukan pemilihan komponen satuan.
- Jika tool recommend_build atau tool pencarian mengembalikan error atau tidak menemukan hasil, jelaskan dengan jujur bahwa budget tidak cukup atau data tidak ditemukan.
- Jika user meminta CPU/GPU tertentu (misal: Ryzen 5, RTX 3060) dan hasil recommend_build tidak cocok:
  1. Jelaskan secara jujur bahwa budget tidak mencukupi.
  2. Gunakan search_cpu/search_gpu untuk menampilkan harga asli komponen yang diminta.
- Jika Anda ingin memanggil tool, balas HANYA dengan objek JSON tool call. Jangan menulis teks apapun sebelum atau sesudah JSON.
- PENCARIAN SPESIFIK: Jika user menanyakan ketersediaan komponen tertentu (misal: "MSI B550M-A PRO ada gak?"), JANGAN batasi parameter max_price pada tool search, agar pencarian tidak terhambat oleh budget rekomendasi sebelumnya.
- Jangan asal jawab harga atau spek tanpa tool jika pertanyaan menyangkut data toko.
- Kalau pertanyaan umum (misal "apa itu DDR5?"), boleh langsung final_answer tanpa tool.
- Jika user tidak menyebut game spesifik, panggil recommend_build tanpa game_id.
- Selalu format harga sebagai "Rp X.XXX.XXX".
- Jika hasil tool kosong/null, sampaikan dengan sopan bahwa data tidak ditemukan.
- STRUKTUR TULISAN:
  1. Tampilkan daftar komponen dengan format bullet-point (tanda *).
  2. Berikan baris baru untuk setiap komponen. Dilarang menuliskan daftar dalam satu paragraf panjang.
  3. Gunakan **bold** pada label komponen, nama model, dan harga.
  4. Berikan baris kosong di antara paragraf pembuka, daftar komponen, dan bagian penutup.
PROMPT;
    }

    private function executeTool(string $tool, array $params): mixed
    {
        return match ($tool) {
            'search_cpu' => $this->searchCpu($params),
            'search_gpu' => $this->searchGpu($params),
            'search_ram' => $this->searchRam($params),
            'search_psu' => $this->searchPsu($params),
            'search_motherboard' => $this->searchMotherboard($params),
            'search_ssd' => $this->searchSsd($params),
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
        $cpu = Cpu::find($params['cpu_id'] ?? null);
        $mobo = Motherboard::find($params['motherboard_id'] ?? null);
        $ram = Ram::find($params['ram_id'] ?? null);
        $gpu = Gpu::find($params['gpu_id'] ?? null);
        $psu = Psu::find($params['psu_id'] ?? null);

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
        $budget = (int) ($params['budget'] ?? 0);
        $game = Game::find($params['game_id'] ?? null);
        $resolution = $params['resolution'] ?? '1080p';

        if (!$game) {
            // Gunakan game acuan populer jika user tidak menyertakan game secara spesifik
            $game = Game::where('games.name', 'like', '%GTA%')->first()
                ?: Game::where('games.weight_class', 'medium')->first()
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
        // PENTING: id disertakan agar AI dapat memanggil check_compatibility setelah rekomendasi
        return [
            'cpu' => [
                'id'    => $res['build']['cpu']['id'] ?? null,
                'name'  => $res['build']['cpu']['name'] ?? '-',
                'price' => $res['build']['cpu']['price'] ?? 0,
            ],
            'gpu' => [
                'id'    => $res['build']['gpu']['id'] ?? null,
                'name'  => $res['build']['gpu']['name'] ?? '-',
                'price' => $res['build']['gpu']['price'] ?? 0,
            ],
            'motherboard' => [
                'id'    => $res['build']['motherboard']['id'] ?? null,
                'name'  => $res['build']['motherboard']['name'] ?? '-',
                'price' => $res['build']['motherboard']['price'] ?? 0,
            ],
            'ram' => [
                'id'    => $res['build']['ram']['id'] ?? null,
                'name'  => $res['build']['ram']['name'] ?? '-',
                'price' => $res['build']['ram']['price'] ?? 0,
            ],
            'ssd' => [
                'id'    => $res['build']['ssd']['id'] ?? null,
                'name'  => $res['build']['ssd']['name'] ?? '-',
                'price' => $res['build']['ssd']['price'] ?? 0,
            ],
            'psu' => [
                'id'    => $res['build']['psu']['id'] ?? null,
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
            $query->where('cpus.name', 'like', '%' . $params['keyword'] . '%');
        }
        if (!empty($params['max_price'])) {
            $query->where('cpus.price', '<=', $params['max_price']);
        }
        return $query->orderBy('cpus.price', 'desc')->limit(5)
            ->get(['id', 'name', 'socket', 'cores', 'threads', 'tdp', 'price'])
            ->toArray();
    }

    private function searchGpu(array $params): array
    {
        $query = Gpu::query();
        if (!empty($params['keyword'])) {
            $query->where('gpus.name', 'like', '%' . $params['keyword'] . '%');
        }
        if (!empty($params['max_price'])) {
            $query->where('gpus.price', '<=', $params['max_price']);
        }
        return $query->orderBy('gpus.price', 'desc')->limit(5)
            ->get(['id', 'name', 'vram', 'power_draw', 'price'])
            ->toArray();
    }

    private function searchRam(array $params): array
    {
        $query = Ram::query();
        if (!empty($params['keyword'])) {
            $query->where('rams.name', 'like', '%' . $params['keyword'] . '%');
        }
        if (!empty($params['max_price'])) {
            $query->where('rams.price', '<=', $params['max_price']);
        }
        return $query->orderBy('rams.price', 'desc')->limit(5)
            ->get(['id', 'name', 'ddr_version', 'capacity', 'speed', 'sticks', 'price'])
            ->toArray();
    }

    private function searchPsu(array $params): array
    {
        $query = Psu::query();
        if (!empty($params['keyword'])) {
            $query->where('psus.name', 'like', '%' . $params['keyword'] . '%');
        }
        if (!empty($params['max_price'])) {
            $query->where('psus.price', '<=', $params['max_price']);
        }
        return $query->orderBy('psus.price', 'desc')->limit(5)
            ->get(['id', 'name', 'watt', 'certification', 'price'])
            ->toArray();
    }

    private function searchGame(array $params): array
    {
        return Game::where('games.name', 'like', '%' . ($params['keyword'] ?? '') . '%')
            ->limit(5)
            ->get(['id', 'name', 'weight_class'])
            ->toArray();
    }

    private function searchMotherboard(array $params): array
    {
        $query = Motherboard::query();
        if (!empty($params['keyword'])) {
            $query->where('motherboards.name', 'like', '%' . $params['keyword'] . '%');
        }
        if (!empty($params['max_price'])) {
            $query->where('motherboards.price', '<=', $params['max_price']);
        }
        return $query->orderBy('motherboards.price', 'desc')->limit(5)
            ->get(['id', 'name', 'socket', 'chipset', 'ram_type', 'max_ram', 'price'])
            ->toArray();
    }

    private function searchSsd(array $params): array
    {
        $query = Ssd::query();
        if (!empty($params['keyword'])) {
            $query->where('ssds.name', 'like', '%' . $params['keyword'] . '%');
        }
        if (!empty($params['max_price'])) {
            $query->where('ssds.price', '<=', $params['max_price']);
        }
        return $query->orderBy('ssds.price', 'desc')->limit(5)
            ->get(['id', 'name', 'type', 'capacity', 'power_draw', 'price'])
            ->toArray();
    }

    private function callGroq(array $messages, string $apiKey): ?string
    {
        $messages = $this->sanitizeMessages($messages);

        // Daftar model fallback (dicoba urut jika 429 atau error)
        $models = [
            self::GROQ_MODEL,                             // llama-3.3-70b-versatile (utama)
            'meta-llama/llama-4-scout-17b-16e-instruct', // fallback 1
            'llama-3.1-8b-instant',                       // fallback 2
        ];

        try {
            $lastResponse = null;

            foreach ($models as $index => $model) {
                if ($index > 0) {
                    $waitSeconds = 2;
                    Log::info("ChatAssistant: Groq 429 rate limit or failure. Menunggu {$waitSeconds} detik lalu coba model: {$model}");
                    sleep($waitSeconds);
                }

                try {
                    $response = Http::withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json',
                    ])->connectTimeout(5)->timeout(8)->post('https://api.groq.com/openai/v1/chat/completions', [
                        'model' => $model,
                        'messages' => $messages,
                        'temperature' => 0.3,
                        'max_tokens' => 1024,
                    ]);

                    $lastResponse = $response;

                    if ($response->successful()) {
                        Log::info("ChatAssistant: Berhasil dengan model: {$model}");
                        return trim($response->json('choices.0.message.content') ?? '');
                    }

                    $statusCode = $response->status();
                    // Lanjut ke model berikutnya jika 429 (rate limit) atau 400/404/503 (model error)
                    if (!in_array($statusCode, [429, 400, 404, 503])) {
                        // Error tidak terduga — tidak perlu coba model lain
                        break;
                    }

                    Log::warning("ChatAssistant: Model {$model} gagal (HTTP {$statusCode}), mencoba model berikutnya.");
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    Log::warning("ChatAssistant: Model {$model} mengalami timeout/koneksi gagal: " . $e->getMessage() . ", mencoba model berikutnya.");
                }
            }

            // Semua model gagal
            Log::channel('chat_errors')->error('[ChatAssistant] Groq API error', [
                'status' => $lastResponse?->status(),
                'body' => $lastResponse?->body(),
                'api_key_hint' => substr($apiKey, 0, 10) . '...',
            ]);

        } catch (\Exception $e) {
            Log::channel('chat_errors')->error('[ChatAssistant] Groq exception', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
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

    private function cleanReply(string $reply): string
    {
        // List of common phrases to remove if they shouldn't be there
        $phrasesToRemove = [
            'Silakan pilih nomor yang Anda inginkan.',
            'Silakan pilih nomor.',
            'Pilih nomor yang Anda inginkan.',
            'Silakan pilih nomor rakitan yang Anda inginkan.',
        ];

        $lowerReply = strtolower($reply);

        // Check if it looks like a full build recommendation or lacks a numbered list
        $hasComponentLabels = false;
        // Check for multiple keywords indicating a full build recommendation
        $componentsFound = 0;
        $keywords = ['prosesor', 'cpu', 'vga', 'gpu', 'mainboard', 'motherboard', 'ram', 'ssd', 'psu', 'power supply'];
        foreach ($keywords as $kw) {
            if (str_contains($lowerReply, $kw)) {
                $componentsFound++;
            }
        }
        // If there are at least 3 distinct component keywords, it's likely a build list
        if ($componentsFound >= 3) {
            $hasComponentLabels = true;
        }

        // Check if it lacks a numbered list (e.g., "1. " or "2. " or "1) " at the start of a line)
        // We look for a line starting with a digit 1-5 followed by a dot or parenthesis, like: 1. or 2)
        $hasNumberedList = (bool) preg_match('/^\s*[1-5][\.\)]\s+/m', $reply);

        if ($hasComponentLabels || !$hasNumberedList) {
            foreach ($phrasesToRemove as $phrase) {
                // Remove the phrase (case-insensitive and handle surrounding whitespace/newlines)
                $pattern = '/\s*' . preg_quote($phrase, '/') . '\s*/i';
                $reply = preg_replace($pattern, ' ', $reply);
            }
            $reply = trim($reply);
        }

        return $reply;
    }
}
