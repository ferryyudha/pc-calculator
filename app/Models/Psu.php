<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Psu extends Model
{
    protected $fillable = [
        'name', 'watt', 'certification', 'price',
    ];

    protected $casts = [
        'watt'  => 'integer',
        'price' => 'integer',
    ];

    public function buildRecommendations(): HasMany
    {
        return $this->hasMany(BuildRecommendation::class);
    }
}
