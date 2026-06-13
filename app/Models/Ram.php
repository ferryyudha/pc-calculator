<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ram extends Model
{
    protected $fillable = [
        'name', 'ddr_version', 'capacity', 'speed', 'sticks', 'price',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'speed'    => 'integer',
        'sticks'   => 'integer',
        'price'    => 'integer',
    ];

    public function buildRecommendations(): HasMany
    {
        return $this->hasMany(BuildRecommendation::class);
    }
}
