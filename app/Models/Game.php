<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'name', 'slug', 'cover_image', 'weight_class', 'min_vram',
    ];

    protected $casts = [
        'min_vram' => 'integer',
    ];

    /**
     * Accessor untuk nama game untuk membersihkan pola (id: X).
     */
    public function getNameAttribute($value)
    {
        return preg_replace('/\s*\(id:\s*\d+\)/i', '', $value);
    }

    public function benchmarks(): HasMany
    {
        return $this->hasMany(Benchmark::class);
    }

    public function buildRecommendations(): HasMany
    {
        return $this->hasMany(BuildRecommendation::class);
    }
}
