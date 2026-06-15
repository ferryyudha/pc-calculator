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
}
