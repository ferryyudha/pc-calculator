<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hdd extends Model
{
    protected $fillable = [
        'name', 'capacity', 'power_draw', 'price',
    ];

    protected $casts = [
        'capacity'   => 'integer',
        'power_draw' => 'float',
        'price'      => 'integer',
    ];
}
