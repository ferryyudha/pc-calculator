<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Battery extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'design_capacity',
        'full_charge_capacity',
        'health_percentage',
        'cycle_count',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
