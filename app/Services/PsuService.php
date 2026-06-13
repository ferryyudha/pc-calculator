<?php

namespace App\Services;

use App\Models\Cpu;
use App\Models\Gpu;
use App\Models\Ssd;
use App\Models\Hdd;
use App\Models\Psu;

/**
 * PsuService — Blueprint §3.2 PSU Calculator Formula
 *
 * PSU Calculator Formula:
 * =============================================
 *
 * Konsumsi komponen utama (dari database)
 *   cpu_power    = cpu.tdp              (Watt)
 *   gpu_power    = gpu.power_draw       (Watt)
 *
 * Konsumsi storage (dari database jika pilih spesifik, atau default jika input jumlah)
 *   ssd_power    = sum(selected_ssd.power_draw)  atau: jumlah_ssd × 5W (NVMe default)
 *   hdd_power    = sum(selected_hdd.power_draw)  atau: jumlah_hdd × 8W (default)
 *
 * Konsumsi tambahan (konstanta)
 *   fan_power    = jumlah_fan × 3      (Watt per fan)
 *   motherboard  = 30                   (Watt estimasi rata-rata)
 *   ram_power    = 5                    (Watt estimasi)
 *
 * Total = cpu + gpu + storage + fans + motherboard + ram
 * Recommended raw = total × 1.3 (30% headroom)
 * Round up to nearest tier [450, 550, 650, 750, 850]
 * If > 850W, return 850W with warning
 *
 * MAINTENANCE NOTE: Untuk mengubah konstanta power estimasi (misal headroom dari 30% ke 20%),
 * cukup edit konstanta class di bawah (HEADROOM_FACTOR, DEFAULT_SSD_W, dll.)
 * tanpa perlu mengubah logika kalkulasi.
 */
class PsuService
{
    // Tier PSU yang tersedia di pasaran (dalam Watt)
    // MAINTENANCE NOTE: Tambah tier baru jika ingin mendukung PSU > 850W
    private const PSU_TIERS       = [450, 550, 650, 750, 850];
    private const DEFAULT_SSD_W   = 5.0;   // NVMe default (gunakan ini jika user hanya input jumlah)
    private const DEFAULT_HDD_W   = 8.0;   // HDD default
    private const FAN_WATT        = 3.0;   // Per fan (case fan rata-rata 3W)
    private const MOTHERBOARD_W   = 30;    // Estimasi konsumsi motherboard tanpa komponen
    private const RAM_W           = 5;     // Estimasi konsumsi RAM (per sistem, bukan per stick)
    private const HEADROOM_FACTOR = 1.3;   // 30% headroom untuk efisiensi dan ketahanan PSU

    public function calculate(
        Cpu $cpu,
        Gpu $gpu,
        array $ssdIds = [],
        array $hddIds = [],
        int $ssdCount = 0,
        int $hddCount = 0,
        int $fans = 3
    ): array {
        // CPU dan GPU: ambil TDP dari database (harus diisi akurat di seeder)
        $cpuPower = $cpu->tdp;
        $gpuPower = $gpu->power_draw;

        // Storage: gunakan data spesifik dari database jika ID diberikan,
        // fallback ke default watt × jumlah jika user hanya input angka
        if (!empty($ssdIds)) {
            $ssdPower = (float) Ssd::whereIn('id', $ssdIds)->sum('power_draw');
        } else {
            $ssdPower = $ssdCount * self::DEFAULT_SSD_W;
        }

        if (!empty($hddIds)) {
            $hddPower = (float) Hdd::whereIn('id', $hddIds)->sum('power_draw');
        } else {
            $hddPower = $hddCount * self::DEFAULT_HDD_W;
        }

        $storagePower   = $ssdPower + $hddPower;
        $fanPower       = $fans * self::FAN_WATT;
        $overheadPower  = self::MOTHERBOARD_W + self::RAM_W;
        $totalDraw      = $cpuPower + $gpuPower + $storagePower + $fanPower + $overheadPower;
        // Kalikan total dengan headroom factor untuk mendapat rekomendasi minimum watt
        $recommendedRaw = (int) ceil($totalDraw * self::HEADROOM_FACTOR);

        // Cari tier PSU terendah yang masih mencukupi kebutuhan watt
        $recommendedWatt = 850; // fallback ke max jika semua tier tidak mencukupi
        $overLimit = false;
        foreach (self::PSU_TIERS as $tier) {
            if ($tier >= $recommendedRaw) {
                $recommendedWatt = $tier;
                break;
            }
        }
        // Tandai over-limit jika kebutuhan melebihi tier maksimum (850W)
        if ($recommendedRaw > 850) {
            $overLimit = true;
        }

        // Ambil data PSU yang direkomendasikan dari database berdasarkan watt yang dipilih
        $psu = Psu::where('watt', $recommendedWatt)->first();

        return [
            'breakdown' => [
                'cpu'         => $cpuPower,
                'gpu'         => $gpuPower,
                'storage'     => (float) $storagePower,
                'fans'        => (float) $fanPower,
                'overhead'    => $overheadPower,
                'total_draw'  => (float) $totalDraw,
            ],
            'recommended_watt' => $recommendedWatt,
            'recommended_psu'  => $psu ? [
                'id'            => $psu->id,
                'name'          => $psu->name,
                'watt'          => $psu->watt,
                'certification' => $psu->certification,
                'price'         => $psu->price,
            ] : null,
            'over_limit' => $overLimit,
            'warning'    => $overLimit
                ? "Konsumsi daya melebihi 850W. Pertimbangkan build yang lebih hemat energi."
                : null,
        ];
    }
}
