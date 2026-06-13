<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Device;
use App\Models\HardwareLog;
use App\Models\StorageDevice;
use App\Models\Battery;
use App\Models\Alert;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'device_code' => 'required|string|max:50',
            'hostname' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'os' => 'nullable|string|max:100',
        ]);

        $deviceCode = $request->input('device_code');

        // Check if device already exists
        $device = Device::where('device_code', $deviceCode)->first();

        if ($device) {
            // Update hostname, OS, and IP if changed
            $device->update([
                'hostname' => $request->input('hostname', $device->hostname),
                'serial_number' => $request->input('serial_number', $device->serial_number),
                'os' => $request->input('os', $device->os),
                'ip_address' => $request->ip(),
                'status' => 'online',
                'last_seen' => now(),
            ]);
        } else {
            // Register a new device with unique API Key
            $device = Device::create([
                'device_code' => $deviceCode,
                'hostname' => $request->input('hostname'),
                'serial_number' => $request->input('serial_number'),
                'os' => $request->input('os'),
                'ip_address' => $request->ip(),
                'api_key' => Str::random(64),
                'status' => 'online',
                'last_seen' => now(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Device registered successfully',
            'api_key' => $device->api_key,
        ]);
    }

    public function report(Request $request)
    {
        /** @var Device $device */
        $device = $request->input('authenticated_device');

        // Update heartbeat info
        $device->update([
            'status' => 'online',
            'last_seen' => now(),
            'ip_address' => $request->ip(),
        ]);

        // 1. Log Hardware Metrics
        $cpuPower = (float) $request->input('cpu_power', 0);
        $gpuPower = (float) $request->input('gpu_power', 0);
        // Default power approximations from blueprint if not supplied
        $ramPower = 10.0;
        $ssdPower = 5.0;
        $moboPower = 20.0;
        
        $totalPower = (float) $request->input('power_usage');
        if (!$totalPower) {
            $totalPower = $cpuPower + $gpuPower + $ramPower + $ssdPower + $moboPower;
        }

        $log = HardwareLog::create([
            'device_id' => $device->id,
            'cpu_temp' => $request->input('cpu_temp'),
            'cpu_usage' => $request->input('cpu_usage'),
            'cpu_voltage' => $request->input('cpu_voltage'),
            'cpu_power' => $cpuPower,
            'gpu_temp' => $request->input('gpu_temp'),
            'gpu_usage' => $request->input('gpu_usage'),
            'gpu_power' => $gpuPower,
            'ram_usage' => $request->input('ram_usage'), // in GB
            'power_usage' => $totalPower,
        ]);

        // 2. Handle Storage Devices
        $storageDevices = $request->input('storage_devices', []);
        if (is_array($storageDevices)) {
            $reportedNames = [];
            foreach ($storageDevices as $sData) {
                if (!isset($sData['name'])) continue;
                $reportedNames[] = $sData['name'];

                $storage = StorageDevice::updateOrCreate(
                    [
                        'device_id' => $device->id,
                        'name' => $sData['name']
                    ],
                    [
                        'capacity' => $sData['capacity'] ?? 0,
                        'health' => $sData['health'] ?? 100,
                        'temperature' => $sData['temperature'] ?? null,
                        'power_on_hours' => $sData['power_on_hours'] ?? null,
                    ]
                );

                // Alert rule: SSD Health < 70%
                if (isset($sData['health']) && $sData['health'] < 70) {
                    $this->triggerAlert(
                        $device,
                        'ssd_health',
                        "Storage '{$sData['name']}' health is critical: {$sData['health']}%."
                    );
                }
            }

            // Prune stale storage devices that are no longer reported
            StorageDevice::where('device_id', $device->id)
                ->whereNotIn('name', $reportedNames)
                ->delete();
        }

        // 3. Handle Battery
        $batteryData = $request->input('battery');
        if (is_array($batteryData) && isset($batteryData['design_capacity'])) {
            Battery::updateOrCreate(
                ['device_id' => $device->id],
                [
                    'design_capacity' => $batteryData['design_capacity'],
                    'full_charge_capacity' => $batteryData['full_charge_capacity'] ?? null,
                    'health_percentage' => $batteryData['health_percentage'] ?? null,
                    'cycle_count' => $batteryData['cycle_count'] ?? null,
                ]
            );
        }

        // 4. Alert Checks
        $cpuTemp = $request->input('cpu_temp');
        if ($cpuTemp && $cpuTemp > 90) {
            $this->triggerAlert(
                $device,
                'overheat_cpu',
                "CPU Overheat warning: Current temperature is {$cpuTemp}°C (Limit: 90°C)."
            );
        }

        $gpuTemp = $request->input('gpu_temp');
        if ($gpuTemp && $gpuTemp > 85) {
            $this->triggerAlert(
                $device,
                'overheat_gpu',
                "GPU Overheat warning: Current temperature is {$gpuTemp}°C (Limit: 85°C)."
            );
        }

        // 5. Handle Diagnostics
        $diagnostics = $request->input('diagnostics', []);
        if (is_array($diagnostics)) {
            foreach ($diagnostics as $diag) {
                $status = $diag['status'] ?? 'ok';
                if ($status === 'warning' || $status === 'critical') {
                    $component = strtolower($diag['component'] ?? 'system');
                    $msg = $diag['message'] ?? '';
                    $sugg = $diag['suggestion'] ?? '';
                    
                    $fullMessage = $sugg ? "{$msg} — {$sugg}" : $msg;
                    
                    $this->triggerAlert(
                        $device,
                        "diagnostic_{$component}",
                        $fullMessage,
                        'diagnostic'
                    );
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Telemetry report saved.',
        ]);
    }

    public function ping(Request $request)
    {
        /** @var Device $device */
        $device = $request->input('authenticated_device');

        $device->update([
            'status' => 'online',
            'last_seen' => now(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Heartbeat received.',
        ]);
    }

    private function triggerAlert(Device $device, string $type, string $message, string $source = 'agent_rule')
    {
        // Deduplicate: check if there's already an active or acknowledged alert of this type
        $existingAlert = Alert::where('device_id', $device->id)
            ->where('type', $type)
            ->whereIn('status', ['active', 'acknowledged'])
            ->first();

        if (!$existingAlert) {
            Alert::create([
                'device_id' => $device->id,
                'type' => $type,
                'message' => $message,
                'status' => 'active',
                'source' => $source,
            ]);
        } else {
            // Update message and source if details changed
            $existingAlert->update([
                'message' => $message,
                'source' => $source,
            ]);
        }
    }
}
