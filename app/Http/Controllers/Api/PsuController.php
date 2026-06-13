<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cpu;
use App\Models\Gpu;
use App\Services\PsuService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PsuController extends Controller
{
    use ApiResponse;

    public function __construct(private PsuService $service) {}

    /**
     * POST /api/psu-calculate
     *
     * Supports two modes:
     *  Mode 1: user selects specific SSD/HDD IDs (ssd_ids, hdd_ids)
     *  Mode 2: user inputs count only (ssd_count, hdd_count)
     */
    public function calculate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cpu_id'    => 'required|integer|exists:cpus,id',
            'gpu_id'    => 'required|integer|exists:gpus,id',
            'ssd_ids'   => 'sometimes|array',
            'ssd_ids.*' => 'integer|exists:ssds,id',
            'hdd_ids'   => 'sometimes|array',
            'hdd_ids.*' => 'integer|exists:hdds,id',
            'ssd_count' => 'sometimes|integer|min:0|max:10',
            'hdd_count' => 'sometimes|integer|min:0|max:10',
            'fans'      => 'sometimes|integer|min:0|max:20',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Input request tidak valid', $validator->errors());
        }

        $cpu = Cpu::findOrFail($request->cpu_id);
        $gpu = Gpu::findOrFail($request->gpu_id);

        $result = $this->service->calculate(
            cpu:      $cpu,
            gpu:      $gpu,
            ssdIds:   $request->input('ssd_ids', []),
            hddIds:   $request->input('hdd_ids', []),
            ssdCount: (int) $request->input('ssd_count', 0),
            hddCount: (int) $request->input('hdd_count', 0),
            fans:     (int) $request->input('fans', 3),
        );

        return $this->success($result);
    }
}
