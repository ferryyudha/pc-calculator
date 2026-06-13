<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cpu;
use App\Models\Gpu;
use App\Models\Game;
use App\Services\FpsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FpsController extends Controller
{
    use ApiResponse;

    public function __construct(private FpsService $service) {}

    /** POST /api/fps-estimate */
    public function estimate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cpu_id'     => 'required|integer|exists:cpus,id',
            'gpu_id'     => 'required|integer|exists:gpus,id',
            'game_id'    => 'required|integer|exists:games,id',
            'resolution' => 'required|in:720p,1080p,1440p,4K',
            'ram_id'     => 'nullable|integer|exists:rams,id',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Input request tidak valid', $validator->errors());
        }

        $cpu   = Cpu::find($request->cpu_id);
        $gpu   = Gpu::find($request->gpu_id);
        $game  = Game::find($request->game_id);

        $result = $this->service->estimate($cpu->id, $gpu->id, $game->id, $request->resolution, $request->ram_id);

        if (!$result) {
            return $this->error(
                'BENCHMARK_NOT_FOUND',
                'Data benchmark untuk kombinasi ini belum tersedia',
                null,
                404
            );
        }

        return $this->success(
            [
                'game'       => $game->name,
                'resolution' => $request->resolution,
                'fps'        => $result['fps'],
                'cpu'        => ['id' => $cpu->id, 'name' => $cpu->name],
                'gpu'        => ['id' => $gpu->id, 'name' => $gpu->name],
            ],
            [
                'source'      => $result['source'],
                'note'        => $result['note'],
                'ram_applied' => $result['ram_applied'] ?? false,
                'ram_factor'  => $result['ram_factor'] ?? null,
            ]
        );
    }

    /** POST /api/fps-estimate-all */
    public function estimateAll(Request $request): JsonResponse
    {
        $gpuRule = 'required|integer|exists:gpus,id';
        if ($request->gpu_id === 'igpu') {
            $gpuRule = 'required';
        }

        $validator = Validator::make($request->all(), [
            'cpu_id' => 'required|integer|exists:cpus,id',
            'gpu_id' => $gpuRule,
            'ram_id' => 'nullable|integer|exists:rams,id',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Input request tidak valid', $validator->errors());
        }

        $cpu = Cpu::find($request->cpu_id);
        $games = Game::all();
        $resolutions = ['720p', '1080p', '4K'];
        
        $data = [];

        foreach ($games as $game) {
            $fpsData = [];
            foreach ($resolutions as $res) {
                $fps = null;
                if ($request->gpu_id !== 'igpu') {
                    $gpu = Gpu::find($request->gpu_id);
                    if ($gpu && $cpu) {
                        $fps = $this->service->estimate($cpu->id, $gpu->id, $game->id, $res, $request->ram_id);
                    }
                }
                
                if ($fps) {
                    $fpsData[$res] = $fps['fps']['high']; // Use High preset as representative FPS
                } else {
                    $fpsData[$res] = null;
                }
            }
            
            $data[] = [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'game_slug' => $game->slug,
                'weight_class' => $game->weight_class,
                'fps' => $fpsData
            ];
        }

        $ramFactor = null;
        $ramApplied = false;
        if ($request->ram_id) {
            $ram = \App\Models\Ram::find($request->ram_id);
            if ($ram) {
                $ramFactor = $this->service->getRamFactor($ram);
                $ramApplied = true;
            }
        }

        return $this->success($data, [
            'ram_applied' => $ramApplied,
            'ram_factor'  => $ramFactor,
        ]);
    }
}
