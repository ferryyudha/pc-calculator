<?php

namespace App\Services;

use App\Models\Cpu;
use App\Models\Gpu;

class PsuRequirementCalculator
{
    public function calculate(Cpu $cpu, Gpu $gpu): int
    {
        $base = (int) ceil((($cpu->tdp + $gpu->power_draw) * 1.4) + 120);
        return max($base, $gpu->min_recommended_psu ?? 0);
    }
}
