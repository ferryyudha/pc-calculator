<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorageDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'name',
        'capacity',
        'health',
        'temperature',
        'power_on_hours',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
