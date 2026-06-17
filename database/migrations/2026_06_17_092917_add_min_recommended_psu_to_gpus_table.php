<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gpus', function (Blueprint $table) {
            $table->integer('min_recommended_psu')->nullable()->after('price');
        });

        // Run data update
        $gpus = \Illuminate\Support\Facades\DB::table('gpus')->get();
        foreach ($gpus as $gpu) {
            $name = $gpu->name;
            $lower = strtolower($name);

            // 1. Correct the Brand
            $brand = 'NVIDIA';
            if (
                str_contains($lower, 'radeon') ||
                str_contains($lower, 'rx ') ||
                str_contains($lower, 'amd') ||
                str_contains($lower, 'firepro') ||
                (str_contains($lower, 'r7') && !preg_match('/g?ddr7/i', $lower)) ||
                (str_contains($lower, 'r9') && !preg_match('/g?ddr9/i', $lower)) ||
                (str_contains($lower, 'r5') && !preg_match('/g?ddr5/i', $lower)) ||
                str_contains($lower, 'hd ') ||
                str_contains($lower, 'hd5') ||
                str_contains($lower, 'hd6') ||
                str_contains($lower, 'hd7')
            ) {
                $brand = 'AMD';
            } elseif (str_contains($lower, 'intel') || str_contains($lower, 'arc ')) {
                $brand = 'Intel';
            }

            // 2. Determine specs (power_draw & min_recommended_psu)
            $powerDraw = 150;
            $minPsu = 500;

            if (str_contains($lower, '5090')) {
                $powerDraw = 600;
                $minPsu = 1000;
            } elseif (str_contains($lower, '5080')) {
                $powerDraw = 360;
                $minPsu = 800;
            } elseif (str_contains($lower, '5070 ti')) {
                $powerDraw = 250;
                $minPsu = 700;
            } elseif (str_contains($lower, '5070')) {
                $powerDraw = 220;
                $minPsu = 650;
            } elseif (str_contains($lower, '5060 ti')) {
                $powerDraw = 180;
                $minPsu = 600;
            } elseif (str_contains($lower, '5060')) {
                $powerDraw = 140;
                $minPsu = 550;
            } elseif (str_contains($lower, '4090')) {
                $powerDraw = 450;
                $minPsu = 850;
            } elseif (str_contains($lower, '4080')) {
                $powerDraw = 320;
                $minPsu = 750;
            } elseif (str_contains($lower, '4070 ti')) {
                $powerDraw = 285;
                $minPsu = 700;
            } elseif (str_contains($lower, '4070 super')) {
                $powerDraw = 220;
                $minPsu = 650;
            } elseif (str_contains($lower, '4070')) {
                $powerDraw = 200;
                $minPsu = 650;
            } elseif (str_contains($lower, '3090')) {
                $powerDraw = 350;
                $minPsu = 750;
            } elseif (str_contains($lower, '3080')) {
                $powerDraw = 320;
                $minPsu = 750;
            } elseif (str_contains($lower, '3070 ti')) {
                $powerDraw = 290;
                $minPsu = 650;
            } elseif (str_contains($lower, '3070')) {
                $powerDraw = 220;
                $minPsu = 650;
            } elseif (str_contains($lower, '3060 ti')) {
                $powerDraw = 200;
                $minPsu = 600;
            } elseif (str_contains($lower, '3060')) {
                $powerDraw = 170;
                $minPsu = 550;
            } elseif (str_contains($lower, '3050')) {
                if (str_contains($lower, '6gb')) {
                    $powerDraw = 75;
                    $minPsu = 300;
                } else {
                    $powerDraw = 130;
                    $minPsu = 450;
                }
            } elseif (str_contains($lower, '1660 super')) {
                $powerDraw = 125;
                $minPsu = 450;
            } elseif (str_contains($lower, '1660')) {
                $powerDraw = 120;
                $minPsu = 450;
            } elseif (str_contains($lower, '960')) {
                $powerDraw = 120;
                $minPsu = 400;
            } elseif (str_contains($lower, '710')) {
                $powerDraw = 19;
                $minPsu = 300;
            } elseif (str_contains($lower, '6900 xt')) {
                $powerDraw = 300;
                $minPsu = 850;
            } elseif (str_contains($lower, '7800 xt')) {
                $powerDraw = 263;
                $minPsu = 700;
            } elseif (str_contains($lower, '6700 xt')) {
                $powerDraw = 230;
                $minPsu = 650;
            } elseif (str_contains($lower, '6600 xt')) {
                $powerDraw = 160;
                $minPsu = 500;
            } elseif (str_contains($lower, '5600 xt') || str_contains($lower, '5600')) {
                $powerDraw = 150;
                $minPsu = 450;
            } elseif (str_contains($lower, '580')) {
                $powerDraw = 185;
                $minPsu = 500;
            } elseif (str_contains($lower, '9060 xt')) {
                $powerDraw = 190;
                $minPsu = 600;
            } elseif (str_contains($lower, '6500 xt')) {
                $powerDraw = 107;
                $minPsu = 400;
            } elseif (str_contains($lower, '9070 gre')) {
                $powerDraw = 260;
                $minPsu = 700;
            } elseif (str_contains($lower, 'b580')) {
                $powerDraw = 190;
                $minPsu = 600;
            }

            \Illuminate\Support\Facades\DB::table('gpus')
                ->where('id', $gpu->id)
                ->update([
                    'brand' => $brand,
                    'power_draw' => $powerDraw,
                    'min_recommended_psu' => $minPsu,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gpus', function (Blueprint $table) {
            $table->dropColumn('min_recommended_psu');
        });
    }
};
