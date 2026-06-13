<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cpu;
use App\Models\Gpu;
use App\Models\Motherboard;
use App\Models\Ram;
use App\Models\Psu;
use App\Services\CompatibilityService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompatibilityController extends Controller
{
    use ApiResponse;

    public function __construct(private CompatibilityService $service) {}

    /** POST /api/check-compatibility */
    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cpu_id'          => 'required|integer|exists:cpus,id',
            'motherboard_id'  => 'required|integer|exists:motherboards,id',
            'ram_id'          => 'required|integer|exists:rams,id',
            'gpu_id'          => 'required|integer|exists:gpus,id',
            'psu_id'          => 'required|integer|exists:psus,id',
        ]);

        if ($validator->fails()) {
            return $this->error(
                'VALIDATION_ERROR',
                'Input request tidak valid',
                $validator->errors(),
                422
            );
        }

        $cpu  = Cpu::find($request->cpu_id);
        $mobo = Motherboard::find($request->motherboard_id);
        $ram  = Ram::find($request->ram_id);
        $gpu  = Gpu::find($request->gpu_id);
        $psu  = Psu::find($request->psu_id);

        $result = $this->service->check($cpu, $mobo, $ram, $gpu, $psu);

        if ($result['compatible']) {
            return $this->success($result);
        }

        // Find the primary error code from the first failing check
        $failedChecks  = collect($result['checks'])->filter(fn($c) => !$c['passed']);
        $primaryFailed = $failedChecks->first()['name'] ?? '';
        $errorCode     = $this->resolveErrorCode($primaryFailed);
        $count         = $failedChecks->count();

        return $this->error(
            $errorCode,
            "Terdapat {$count} masalah kompatibilitas",
            $result,
            422
        );
    }

    private function resolveErrorCode(string $checkName): string
    {
        return match (true) {
            str_contains($checkName, 'Socket')        => 'INCOMPATIBLE_SOCKET',
            str_contains($checkName, 'RAM ↔ CPU')     => 'INCOMPATIBLE_RAM_CPU',
            str_contains($checkName, 'RAM ↔ Mobo')    => 'INCOMPATIBLE_RAM_MOBO',
            str_contains($checkName, 'PSU')           => 'PSU_INSUFFICIENT',
            default                                    => 'INCOMPATIBLE',
        };
    }
}
