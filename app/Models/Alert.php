<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'type',
        'message',
        'status',
        'source',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
