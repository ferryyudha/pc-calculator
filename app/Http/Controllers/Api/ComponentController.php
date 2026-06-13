<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cpu;
use App\Models\Gpu;
use App\Models\Motherboard;
use App\Models\Ram;
use App\Models\Ssd;
use App\Models\Hdd;
use App\Models\Psu;
use App\Models\Game;
use App\Models\Benchmark;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComponentController extends Controller
{
    use ApiResponse;

    /** GET /api/cpus?brand=AMD&socket=AM4&ram_type=DDR4 — Daftar CPU dengan filter opsional */
    public function cpus(Request $request): JsonResponse
    {
        $query = Cpu::query();
        if ($request->brand)    $query->where('brand', $request->brand);
        if ($request->socket)   $query->where('socket', $request->socket);
        if ($request->ram_type) $query->where('ram_type', $request->ram_type);
        return $this->success($query->orderBy('price')->get());
    }

    /** GET /api/gpus?brand=NVIDIA&min_vram=8 — Daftar GPU dengan filter opsional */
    public function gpus(Request $request): JsonResponse
    {
        $query = Gpu::query();
        if ($request->brand)    $query->where('brand', $request->brand);
        if ($request->min_vram) $query->where('vram', '>=', $request->min_vram);
        return $this->success($query->orderBy('price')->get());
    }

    /** GET /api/motherboards?socket=AM4&ram_type=DDR4&chipset=B550 — Daftar motherboard dengan filter */
    public function motherboards(Request $request): JsonResponse
    {
        $query = Motherboard::query();
        if ($request->socket)   $query->where('socket', $request->socket);
        if ($request->ram_type) $query->where('ram_type', $request->ram_type);
        if ($request->chipset)  $query->where('chipset', $request->chipset);
        return $this->success($query->orderBy('price')->get());
    }

    /** GET /api/rams?ddr_version=DDR4 — Daftar RAM dengan filter versi DDR */
    public function rams(Request $request): JsonResponse
    {
        $query = Ram::query();
        if ($request->ddr_version) $query->where('ddr_version', $request->ddr_version);
        return $this->success($query->orderBy('price')->get());
    }

    /** GET /api/ssds?type=NVMe — Daftar SSD dengan filter tipe */
    public function ssds(Request $request): JsonResponse
    {
        $query = Ssd::query();
        if ($request->type) $query->where('type', $request->type);
        return $this->success($query->orderBy('price')->get());
    }

    /** GET /api/hdds — Daftar semua HDD */
    public function hdds(): JsonResponse
    {
        return $this->success(Hdd::orderBy('price')->get());
    }

    /** GET /api/psus?min_watt=650 — Daftar PSU dengan filter minimum watt */
    public function psus(Request $request): JsonResponse
    {
        $query = Psu::query();
        if ($request->min_watt) $query->where('watt', '>=', $request->min_watt);
        return $this->success($query->orderBy('watt')->get());
    }

    /** GET /api/games — Daftar semua game yang didukung */
    public function games(): JsonResponse
    {
        return $this->success(Game::orderBy('name')->get());
    }

    /**
     * GET /api/stats
     * Mengambil jumlah total entri dari tabel-tabel utama di database.
     * Digunakan untuk menampilkan counter statistik dinamis di halaman utama frontend.
     *
     * CATATAN MAINTENANCE: Jika ingin menambah tipe hardware baru ke statistik
     * (misal SSD atau Motherboard), tambahkan query count-nya di sini
     * (contoh: 'ssds' => Ssd::count()) dan perbarui key yang dikembalikan ke API.
     */
    public function stats(): JsonResponse
    {
        return $this->success([
            'cpus' => Cpu::count(),
            'gpus' => Gpu::count(),
            'games' => Game::count(),
            'benchmarks' => Benchmark::count(),
        ]);
    }
}
