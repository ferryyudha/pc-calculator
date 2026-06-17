<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gpu extends Model
{
    protected $fillable = [
        'name', 'brand', 'vram', 'memory_type', 'power_draw', 'price', 'image_category', 'min_recommended_psu',
    ];

    protected $casts = [
        'vram'                => 'integer',
        'power_draw'          => 'integer',
        'price'               => 'integer',
        'min_recommended_psu' => 'integer',
    ];

    public function benchmarks(): HasMany
    {
        return $this->hasMany(Benchmark::class);
    }

    public function buildRecommendations(): HasMany
    {
        return $this->hasMany(BuildRecommendation::class);
    }
}
