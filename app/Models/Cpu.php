<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cpu extends Model
{
    protected $fillable = [
        'name', 'brand', 'socket', 'ram_type',
        'cores', 'threads', 'base_clock', 'boost_clock',
        'tdp', 'price',
    ];

    protected $casts = [
        'base_clock'  => 'float',
        'boost_clock' => 'float',
        'tdp'         => 'integer',
        'price'       => 'integer',
        'cores'       => 'integer',
        'threads'     => 'integer',
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
