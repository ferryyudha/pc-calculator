<?php

namespace App\Services;

use App\Models\Cpu;
use App\Models\Gpu;
use App\Models\Motherboard;
use App\Models\Ram;
use App\Models\Psu;

/**
 * CompatibilityService — Blueprint §3.1
 *
 * Menjalankan semua pengecekan kompatibilitas komponen PC secara berurutan
 * dan mengembalikan SEMUA error sekaligus (bukan berhenti di error pertama).
 *
 * Checks:
 *  1. CPU socket ↔ Motherboard socket
 *  2. CPU ram_type ↔ RAM ddr_version
 *  3. Motherboard ram_type ↔ RAM ddr_version
 *  4. PSU watt >= (cpu.tdp + gpu.power_draw) × 1.2
 *
 * MAINTENANCE NOTE: Untuk menambah pengecekan baru (misal cek PCIe slot),
 * cukup tambahkan blok "$checks[] = [...]" baru di dalam method check() di bawah.
 * Format setiap check: ['name' => string, 'passed' => bool, 'message' => string]
 */
class CompatibilityService
{
    public function __construct(private PsuRequirementCalculator $psuCalculator)
    {
    }

    public function check(Cpu $cpu, Motherboard $mobo, Ram $ram, Gpu $gpu, Psu $psu): array
    {
        $checks = [];

        // Check 1: CPU socket ↔ Motherboard socket
        // Contoh: CPU LGA1700 harus dipasang di mobo LGA1700 (tidak bisa di AM5)
        $socketMatch = $cpu->socket === $mobo->socket;
        $checks[] = [
            'name'    => 'Socket CPU ↔ Motherboard',
            'passed'  => $socketMatch,
            'message' => $socketMatch
                ? "{$cpu->socket} cocok"
                : "CPU menggunakan {$cpu->socket}, Motherboard menggunakan {$mobo->socket}",
        ];

        // Check 2: CPU ram_type ↔ RAM ddr_version
        // cpu.ram_type bisa berupa 'DDR4', 'DDR5', atau 'DDR4/DDR5' (CPU dual-channel)
        $cpuSupportsRam = str_contains($cpu->ram_type, $ram->ddr_version);
        $checks[] = [
            'name'    => 'Tipe RAM ↔ CPU',
            'passed'  => $cpuSupportsRam,
            'message' => $cpuSupportsRam
                ? "{$ram->ddr_version} didukung CPU"
                : "CPU hanya support {$cpu->ram_type}, RAM yang dipilih {$ram->ddr_version}",
        ];

        // Check 3: Motherboard ram_type ↔ RAM ddr_version
        // Mobo DDR4 tidak bisa dipasang RAM DDR5, meski CPU mendukung keduanya
        $moboSupportsRam = $mobo->ram_type === $ram->ddr_version;
        $checks[] = [
            'name'    => 'Tipe RAM ↔ Motherboard',
            'passed'  => $moboSupportsRam,
            'message' => $moboSupportsRam
                ? "{$ram->ddr_version} cocok dengan Motherboard"
                : "Motherboard hanya support {$mobo->ram_type}, RAM yang dipilih {$ram->ddr_version}",
        ];

        // Check 4: PSU watt sufficiency
        $requiredWatt = $this->psuCalculator->calculate($cpu, $gpu);
        $psuSufficient = $psu->watt >= $requiredWatt;
        $checks[] = [
            'name'    => 'PSU Watt',
            'passed'  => $psuSufficient,
            'message' => $psuSufficient
                ? "{$psu->watt}W cukup untuk {$requiredWatt}W total"
                : "Butuh minimal {$requiredWatt}W, PSU yang dipilih hanya {$psu->watt}W",
        ];

        // Sistem kompatibel hanya jika SEMUA check lulus
        $allPassed = collect($checks)->every(fn($c) => $c['passed']);

        return [
            'compatible' => $allPassed,
            'checks'     => $checks,
        ];
    }
}
