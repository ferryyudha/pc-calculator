<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ssd extends Model
{
    protected $fillable = [
        'name', 'type', 'capacity', 'power_draw', 'price',
    ];

    protected $casts = [
        'capacity'   => 'integer',
        'power_draw' => 'float',
        'price'      => 'integer',
    ];
}
