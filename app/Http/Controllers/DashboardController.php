<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Device;
use App\Models\HardwareLog;
use App\Models\StorageDevice;
use App\Models\Battery;
use App\Models\Alert;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getDevices()
    {
        $devices = Device::with(['battery'])
            ->get()
            ->map(function ($device) {
                // Get the latest hardware log
                $latestLog = HardwareLog::where('device_id', $device->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $device->latest_log = $latestLog;
                
                // Determine online status dynamically (last seen within 2 minutes)
                $isOnline = $device->last_seen && $device->last_seen->greaterThanOrEqualTo(now()->subMinutes(2));
                $device->status = $isOnline ? 'online' : 'offline';
                
                return $device;
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $devices
        ]);
    }

    public function getDeviceDetail($id)
    {
        $device = Device::with(['storageDevices', 'battery'])->find($id);

        if (!$device) {
            return response()->json([
                'status' => 'error',
                'message' => 'Device not found.'
            ], 404);
        }

        $latestLog = HardwareLog::where('device_id', $device->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $device->latest_log = $latestLog;

        // Determine online status dynamically
        $isOnline = $device->last_seen && $device->last_seen->greaterThanOrEqualTo(now()->subMinutes(2));
        $device->status = $isOnline ? 'online' : 'offline';

        return response()->json([
            'status' => 'success',
            'data' => $device
        ]);
    }

    public function getDeviceLogs($id, Request $request)
    {
        $device = Device::find($id);

        if (!$device) {
            return response()->json([
                'status' => 'error',
                'message' => 'Device not found.'
            ], 404);
        }

        $timeframe = $request->query('timeframe', '1h'); // 1h, 24h, 7d, 30d
        $query = HardwareLog::where('device_id', $device->id);

        switch ($timeframe) {
            case '24h':
                $query->where('created_at', '>=', now()->subDay());
                // Sample data: approximately every 15 minutes to avoid overloading frontend charts
                // Use modulo on minutes if possible (compatible with SQLite/MySQL)
                if (config('database.default') === 'mysql') {
                    $query->whereRaw('MINUTE(created_at) % 15 = 0');
                }
                break;
            case '7d':
                $query->where('created_at', '>=', now()->subDays(7));
                if (config('database.default') === 'mysql') {
                    $query->whereRaw('MINUTE(created_at) = 0'); // hourly
                }
                break;
            case '30d':
                $query->where('created_at', '>=', now()->subDays(30));
                if (config('database.default') === 'mysql') {
                    $query->whereRaw('HOUR(created_at) % 6 = 0 AND MINUTE(created_at) = 0'); // every 6 hours
                }
                break;
            case '1h':
            default:
                $query->where('created_at', '>=', now()->subHour());
                // Return all data (max 60 points)
                break;
        }

        $logs = $query->orderBy('created_at', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'timeframe' => $timeframe,
            'data' => $logs
        ]);
    }

    public function getAlerts()
    {
        $alerts = Alert::with('device')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $alerts
        ]);
    }

    public function updateAlert($id, Request $request)
    {
        $request->validate([
            'status' => 'required|string|in:acknowledged,resolved'
        ]);

        $alert = Alert::find($id);

        if (!$alert) {
            return response()->json([
                'status' => 'error',
                'message' => 'Alert not found.'
            ], 404);
        }

        $alert->update([
            'status' => $request->input('status')
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Alert updated successfully.',
            'data' => $alert
        ]);
    }

    public function getPowerMetrics($id, Request $request)
    {
        $device = Device::find($id);

        if (!$device) {
            return response()->json([
                'status' => 'error',
                'message' => 'Device not found.'
            ], 404);
        }

        // Custom rate or default PLN rate (Rp 1.699,53 / kWh)
        $rate = (float) $request->query('rate', 1699.53);

        $latestLog = HardwareLog::where('device_id', $device->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $latestPower = $latestLog ? (float) $latestLog->power_usage : 0.0;

        // Daily calculations based on 8 hours of usage per blueprint
        $kwhPerDay = ($latestPower * 8) / 1000;
        $costPerDay = $kwhPerDay * $rate;

        // Cumulative calculation in last 24 hours
        $avgPower24h = HardwareLog::where('device_id', $device->id)
            ->where('created_at', '>=', now()->subDay())
            ->avg('power_usage') ?? 0.0;

        $kwhAccumulated24h = ($avgPower24h * 24) / 1000;
        $costAccumulated24h = $kwhAccumulated24h * $rate;

        return response()->json([
            'status' => 'success',
            'data' => [
                'latest_power_w' => $latestPower,
                'kwh_per_day' => round($kwhPerDay, 4),
                'cost_per_day' => round($costPerDay, 2),
                'cost_per_month' => round($costPerDay * 30, 2),
                'cost_per_year' => round($costPerDay * 365, 2),
                'pln_rate' => $rate,
                'historical_24h' => [
                    'avg_power_w' => round($avgPower24h, 2),
                    'kwh_24h' => round($kwhAccumulated24h, 4),
                    'cost_24h' => round($costAccumulated24h, 2)
                ]
            ]
        ]);
    }

    public function downloadAgent()
    {
        $filePath = public_path('ScanDevice_Agent.zip');
        if (file_exists($filePath)) {
            return response()->download($filePath, 'ScanDevice_Agent.zip');
        }
        return response()->json([
            'status' => 'error',
            'message' => 'Agent file not found.'
        ], 404);
    }
}
