<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_code',
        'hostname',
        'serial_number',
        'os',
        'ip_address',
        'api_key',
        'status',
        'last_seen',
    ];

    protected $casts = [
        'last_seen' => 'datetime',
    ];

    public function hardwareLogs()
    {
        return $this->hasMany(HardwareLog::class);
    }

    public function storageDevices()
    {
        return $this->hasMany(StorageDevice::class);
    }

    public function battery()
    {
        return $this->hasOne(Battery::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }
}
