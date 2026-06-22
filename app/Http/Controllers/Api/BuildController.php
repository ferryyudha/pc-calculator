<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\BuildRecommendationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BuildController extends Controller
{
    use ApiResponse;

    public function __construct(private BuildRecommendationService $service) {}

    /** POST /api/recommend-build */
    public function recommend(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'budget'     => 'required|integer|min:1000000',
            'game_id'    => 'required|integer|exists:games,id',
            'resolution' => 'required|in:720p,1080p,1440p,4K',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Input request tidak valid', $validator->errors());
        }

        $game   = Game::findOrFail($request->game_id);
        $result = $this->service->recommend($request->budget, $game, $request->resolution);

        if (!$result) {
            return $this->error(
                'BUDGET_INSUFFICIENT',
                "Budget Rp " . number_format($request->budget, 0, ',', '.') .
                " tidak cukup untuk target {$request->resolution} gaming pada game {$game->name}",
                null,
                422
            );
        }

        return $this->success($result);
    }

    /** POST /api/recommend-build/ai-prompt */
    public function recommendAiPrompt(Request $request, \App\Services\BuildPromptParserService $parser): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Input request tidak valid', $validator->errors());
        }

        $prompt = $request->input('prompt');
        $parsedPrompt = $parser->parse($prompt);

        $budget = $parsedPrompt['budget_max'] ?? $parsedPrompt['budget_min'] ?? null;
        if ($parsedPrompt['budget_max'] && $parsedPrompt['budget_min']) {
            $budget = (int) (($parsedPrompt['budget_max'] + $parsedPrompt['budget_min']) / 2);
        }

        if (!$budget) {
            $budget = 10000000;
        }

        $resolution = $parsedPrompt['resolution'] ?? '1080p';

        $game = null;
        if (!empty($parsedPrompt['game_keywords'])) {
            foreach ($parsedPrompt['game_keywords'] as $keyword) {
                $game = Game::where('name', 'like', '%' . $keyword . '%')->first();
                if ($game) break;
            }
        }

        if (!$game) {
            $game = Game::where('weight_class', 'medium')->first() ?: Game::first();
        }

        $recommendation = $this->service->recommend(
            $budget,
            $game,
            $resolution,
            $parsedPrompt['cpu_preference'] ?? null,
            $parsedPrompt['gpu_preference'] ?? null,
            $parsedPrompt['ram_capacity'] ?? null,
            $parsedPrompt['storage_capacity'] ?? null
        );

        if (!$recommendation) {
            return $this->error(
                'BUDGET_INSUFFICIENT',
                "Budget Rp " . number_format($budget, 0, ',', '.') .
                " tidak cukup untuk target {$resolution} gaming pada game {$game->name}.",
                null,
                422
            );
        }

        return $this->success([
            'recommendation' => $recommendation,
            'parsed_prompt'  => $parsedPrompt,
        ]);
    }

    /** POST /api/recommend-build/tier/{tier} */
    public function recommendTier(Request $request, string $tier): JsonResponse
    {
        $tierBudgets = [
            'entry'       => 5000000,
            'mainstream'  => 12000000,
            'enthusiast'  => 25000000,
        ];

        $tierResolutions = [
            'entry'      => '1080p',
            'mainstream' => '1440p',
            'enthusiast' => '4K',
        ];

        $budget = $tierBudgets[$tier] ?? 12000000;
        $resolution = $tierResolutions[$tier] ?? '1080p';

        $game = Game::where('weight_class', 'medium')->first() ?: Game::first();

        $recommendation = $this->service->recommend($budget, $game, $resolution);

        if (!$recommendation) {
            return $this->error(
                'BUDGET_INSUFFICIENT',
                "Budget Rp " . number_format($budget, 0, ',', '.') .
                " tidak cukup untuk target {$resolution} gaming pada game {$game->name}.",
                null,
                422
            );
        }

        return $this->success($recommendation);
    }

    /** GET /api/recommend-build/popular-tags */
    public function getPopularTags(): JsonResponse
    {
        // 1. Get most recommended CPU IDs from build_recommendations
        $popularCpuIds = \App\Models\BuildRecommendation::select('cpu_id')
            ->selectRaw('count(*) as count')
            ->groupBy('cpu_id')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->pluck('cpu_id')
            ->toArray();

        // 2. Get most recommended GPU IDs from build_recommendations
        $popularGpuIds = \App\Models\BuildRecommendation::select('gpu_id')
            ->selectRaw('count(*) as count')
            ->groupBy('gpu_id')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->pluck('gpu_id')
            ->toArray();

        $cpus = \App\Models\Cpu::whereIn('id', $popularCpuIds)->pluck('name')->toArray();
        $gpus = \App\Models\Gpu::whereIn('id', $popularGpuIds)->pluck('name')->toArray();

        $cpuTags = [];
        $gpuTags = [];

        foreach ($cpus as $name) {
            $tag = $this->extractCpuFamily($name);
            if ($tag && !in_array($tag, $cpuTags)) {
                $cpuTags[] = $tag;
            }
        }

        foreach ($gpus as $name) {
            $tag = $this->extractGpuFamily($name);
            if ($tag && !in_array($tag, $gpuTags)) {
                $gpuTags[] = $tag;
            }
        }

        // Fallbacks
        $defaultCpus = ['i5', 'i7', 'i9', 'Ryzen 5', 'Ryzen 7', 'Ryzen 9', '245k', '265k', '285k'];
        $defaultGpus = ['RTX5090', 'RTX5080', 'RTX5070Ti', 'RTX5060Ti', 'RX7900XT', 'RX9070XT', 'RX9060XT'];

        if (count($cpuTags) < 4) {
            $cpuTags = array_unique(array_merge($cpuTags, $defaultCpus));
        }
        if (count($gpuTags) < 4) {
            $gpuTags = array_unique(array_merge($gpuTags, $defaultGpus));
        }

        return $this->success([
            'cpus' => array_values($cpuTags),
            'gpus' => array_values($gpuTags),
        ]);
    }

    private function extractCpuFamily(string $name): ?string
    {
        if (stripos($name, 'ryzen 5') !== false) return 'Ryzen 5';
        if (stripos($name, 'ryzen 7') !== false) return 'Ryzen 7';
        if (stripos($name, 'ryzen 9') !== false) return 'Ryzen 9';
        if (stripos($name, 'ryzen 3') !== false) return 'Ryzen 3';
        if (stripos($name, 'i5') !== false || stripos($name, 'core i5') !== false) return 'i5';
        if (stripos($name, 'i7') !== false || stripos($name, 'core i7') !== false) return 'i7';
        if (stripos($name, 'i9') !== false || stripos($name, 'core i9') !== false) return 'i9';
        if (stripos($name, '245k') !== false) return '245k';
        if (stripos($name, '265k') !== false) return '265k';
        if (stripos($name, '285k') !== false) return '285k';
        return null;
    }

    private function extractGpuFamily(string $name): ?string
    {
        if (preg_match('/(rtx\s*5090)/i', $name)) return 'RTX5090';
        if (preg_match('/(rtx\s*5080)/i', $name)) return 'RTX5080';
        if (preg_match('/(rtx\s*5070\s*ti)/i', $name)) return 'RTX5070Ti';
        if (preg_match('/(rtx\s*5070)/i', $name)) return 'RTX5070';
        if (preg_match('/(rtx\s*5060\s*ti)/i', $name)) return 'RTX5060Ti';
        if (preg_match('/(rtx\s*5060)/i', $name)) return 'RTX5060';
        if (preg_match('/(rtx\s*4090)/i', $name)) return 'RTX4090';
        if (preg_match('/(rtx\s*4080)/i', $name)) return 'RTX4080';
        if (preg_match('/(rtx\s*4070\s*ti)/i', $name)) return 'RTX4070Ti';
        if (preg_match('/(rtx\s*4070)/i', $name)) return 'RTX4070';
        if (preg_match('/(rtx\s*4060\s*ti)/i', $name)) return 'RTX4060Ti';
        if (preg_match('/(rtx\s*4060)/i', $name)) return 'RTX4060';
        if (preg_match('/(rtx\s*3060)/i', $name)) return 'RTX3060';
        if (preg_match('/(rx\s*7900\s*xt)/i', $name)) return 'RX7900XT';
        if (preg_match('/(rx\s*9070\s*gre)/i', $name)) return 'RX9070GRE';
        if (preg_match('/(rx\s*9070\s*xt)/i', $name)) return 'RX9070XT';
        if (preg_match('/(rx\s*9060\s*xt)/i', $name)) return 'RX9060XT';
        if (preg_match('/(rx\s*6600\s*xt)/i', $name)) return 'RX6600XT';
        if (preg_match('/(rx\s*580)/i', $name)) return 'RX580';
        return null;
    }
}
