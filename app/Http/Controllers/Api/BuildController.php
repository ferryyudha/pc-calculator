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
}
