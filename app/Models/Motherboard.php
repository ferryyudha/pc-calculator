<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Motherboard extends Model
{
    protected $fillable = [
        'name', 'brand', 'socket', 'chipset', 'ram_type', 'max_ram', 'price',
    ];

    protected $casts = [
        'max_ram' => 'integer',
        'price'   => 'integer',
    ];

    public function buildRecommendations(): HasMany
    {
        return $this->hasMany(BuildRecommendation::class);
    }
}
