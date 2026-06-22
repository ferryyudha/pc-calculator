<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BuildPromptParserService
{
    const GROQ_MODEL = 'llama-3.3-70b-versatile';

    public function parse(string $prompt): array
    {
        $apiKey = env('GROQ_API_KEY') ?: config('services.groq.key');

        $systemPrompt = <<<PROMPT
Extract PC build parameters from the user's natural language request.
The user may write in Indonesian or English.

Return ONLY valid JSON, no markdown, no explanation:
{
  "budget_min": <integer in IDR, or null if not specified>,
  "budget_max": <integer in IDR, or null if not specified>,
  "resolution": "<one of: 720p, 1080p, 1440p, 4K, or null>",
  "game_keywords": ["<keywords mentioned, e.g. 'gta v', '4k gaming'>"],
  "cpu_preference": "<CPU name/family mentioned, or null>",
  "gpu_preference": "<GPU name/family mentioned, or null>",
  "ram_capacity": <integer in GB if mentioned, or null>,
  "storage_capacity": <integer in GB if mentioned, or null>
}

Budget conversion rules:
- "$2200-2600" -> convert USD to IDR using rate 1 USD = 16000 IDR
  -> budget_min: 35200000, budget_max: 41600000
- "10 juta" or "10jt" -> 10000000
- "budget 15-20 juta" -> budget_min: 15000000, budget_max: 20000000
- If only one number given, set both budget_min and budget_max to that value

Example input: "play 4k games, rtx5070ti, 1tb, 32gb, budget: \$2200-2600"
Example output:
{
  "budget_min": 35200000,
  "budget_max": 41600000,
  "resolution": "4K",
  "game_keywords": ["4k gaming"],
  "cpu_preference": null,
  "gpu_preference": "RTX 5070 Ti",
  "ram_capacity": 32,
  "storage_capacity": 1000
}
PROMPT;

        $response = null;
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])->connectTimeout(5)->timeout(10)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'           => self::GROQ_MODEL,
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature'     => 0.1,
                'max_tokens'      => 512,
                'response_format' => ['type' => 'json_object'],
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Illuminate\Support\Facades\Log::warning('BuildPromptParser: Primary model connection failed or timed out: ' . $e->getMessage());
        }

        if (!$response || $response->status() === 429) {
            $reason = !$response ? 'failure/timeout' : 'Groq 429 rate limit';
            \Illuminate\Support\Facades\Log::info("BuildPromptParser: {$reason} reached. Falling back to llama-3.1-8b-instant");
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ])->connectTimeout(5)->timeout(10)->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'           => 'llama-3.1-8b-instant',
                    'messages'        => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature'     => 0.1,
                    'max_tokens'      => 512,
                    'response_format' => ['type' => 'json_object'],
                ]);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                \Illuminate\Support\Facades\Log::error('BuildPromptParser: Fallback model connection failed or timed out: ' . $e->getMessage());
                $response = null;
            }
        }

        if ($response && $response->successful()) {
            $content = $response->json('choices.0.message.content');
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        } else {
            $status = $response ? $response->status() : 'No Response';
            $body = $response ? $response->body() : 'No Body';
            \Illuminate\Support\Facades\Log::error('Groq API call failed. Status: ' . $status . ' Body: ' . $body . ' API Key starts with: ' . substr($apiKey, 0, 8));
        }

        // Fallback jika AI gagal
        return [
            'budget_min' => null,
            'budget_max' => null,
            'resolution' => null,
            'game_keywords' => [],
            'cpu_preference' => null,
            'gpu_preference' => null,
            'ram_capacity' => null,
            'storage_capacity' => null,
        ];
    }
}
