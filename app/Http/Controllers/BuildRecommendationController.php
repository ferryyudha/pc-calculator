<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\BuildPromptParserService;
use App\Services\BuildRecommendationService;
use Illuminate\Http\Request;

class BuildRecommendationController extends Controller
{
    public function __construct(
        private BuildPromptParserService $parserService,
        private BuildRecommendationService $recommendationService
    ) {}

    public function index()
    {
        $games = Game::orderBy('name', 'asc')->get();
        $result = null;
        return view('build-recommendation.index', compact('games', 'result'));
    }

    public function parseAndRecommend(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
        ]);

        $prompt = $request->input('prompt');
        $parsedPrompt = $this->parserService->parse($prompt);

        // Calculate budget based on budget_max, budget_min, or fallback
        $budget = $parsedPrompt['budget_max'] ?? $parsedPrompt['budget_min'] ?? null;
        if ($parsedPrompt['budget_max'] && $parsedPrompt['budget_min']) {
            $budget = (int) (($parsedPrompt['budget_max'] + $parsedPrompt['budget_min']) / 2);
        }

        // Fallback budget if not parsed
        if (!$budget) {
            $budget = 10000000;
        }

        $resolution = $parsedPrompt['resolution'] ?? null;

        // Fuzzy match game using game_keywords
        $game = null;
        if (!empty($parsedPrompt['game_keywords'])) {
            foreach ($parsedPrompt['game_keywords'] as $keyword) {
                $game = Game::where('name', 'like', '%' . $keyword . '%')->first();
                if ($game) break;
            }
        }

        // Run the recommendation service
        $recommendation = $this->recommendationService->recommend($budget, $game, $resolution);

        $result = $recommendation;
        if (!$recommendation) {
            $targetDesc = "";
            if ($game && $resolution) {
                $targetDesc = " untuk target {$resolution} gaming pada game {$game->name}";
            } elseif ($game) {
                $targetDesc = " untuk game {$game->name}";
            } elseif ($resolution) {
                $targetDesc = " untuk target resolusi {$resolution}";
            }
            $result = [
                'error' => "Budget Rp " . number_format($budget, 0, ',', '.') . 
                           " tidak cukup" . $targetDesc . ". Silakan naikkan budget Anda.",
            ];
        }

        $games = Game::orderBy('name', 'asc')->get();
        return view('build-recommendation.index', compact('games', 'result', 'parsedPrompt'));
    }

    public function recommendByTier(Request $request, string $tier)
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

        $recommendation = $this->recommendationService->recommend($budget, $game, $resolution);

        $result = $recommendation;
        if (!$recommendation) {
            $result = [
                'error' => "Budget Rp " . number_format($budget, 0, ',', '.') . 
                           " tidak cukup untuk target {$resolution} gaming pada game {$game->name}.",
            ];
        }

        $games = Game::orderBy('name', 'asc')->get();
        return view('build-recommendation.index', compact('games', 'result'));
    }

    public function recommendManual(Request $request)
    {
        $request->validate([
            'budget'     => 'required|integer|min:1000000',
            'game_id'    => 'nullable|integer|exists:games,id',
            'resolution' => 'nullable|in:720p,1080p,1440p,4K',
        ]);

        $budget = (int) $request->input('budget');
        $game = $request->input('game_id') ? Game::find($request->input('game_id')) : null;
        $resolution = $request->input('resolution');

        $recommendation = $this->recommendationService->recommend($budget, $game, $resolution);

        $result = $recommendation;
        if (!$recommendation) {
            $targetDesc = "";
            if ($game && $resolution) {
                $targetDesc = " untuk target {$resolution} gaming pada game {$game->name}";
            } elseif ($game) {
                $targetDesc = " untuk game {$game->name}";
            } elseif ($resolution) {
                $targetDesc = " untuk target resolusi {$resolution}";
            }
            $result = [
                'error' => "Budget Rp " . number_format($budget, 0, ',', '.') . 
                           " tidak cukup" . $targetDesc . ".",
            ];
        }

        $games = Game::orderBy('name', 'asc')->get();
        return view('build-recommendation.index', compact('games', 'result'));
    }
}
