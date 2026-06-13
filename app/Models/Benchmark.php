<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Benchmark extends Model
{
    protected $fillable = [
        'cpu_id', 'gpu_id', 'game_id', 'resolution',
        'fps_low', 'fps_medium', 'fps_high', 'fps_ultra',
        'source', 'is_interpolated',
    ];

    protected $casts = [
        'fps_low'         => 'integer',
        'fps_medium'      => 'integer',
        'fps_high'        => 'integer',
        'fps_ultra'       => 'integer',
        'is_interpolated' => 'boolean',
    ];

    public function cpu(): BelongsTo
    {
        return $this->belongsTo(Cpu::class);
    }

    public function gpu(): BelongsTo
    {
        return $this->belongsTo(Gpu::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
