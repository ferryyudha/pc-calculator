<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HardwareLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'cpu_temp',
        'cpu_usage',
        'cpu_voltage',
        'cpu_power',
        'gpu_temp',
        'gpu_usage',
        'gpu_power',
        'ram_usage',
        'power_usage',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
