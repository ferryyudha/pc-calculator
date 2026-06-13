<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildRecommendation extends Model
{
    protected $fillable = [
        'budget', 'game_id', 'resolution',
        'cpu_id', 'gpu_id', 'motherboard_id', 'ram_id', 'ssd_id', 'psu_id',
        'total_price', 'estimated_fps',
    ];

    protected $casts = [
        'budget'        => 'integer',
        'total_price'   => 'integer',
        'estimated_fps' => 'integer',
    ];

    public function cpu(): BelongsTo        { return $this->belongsTo(Cpu::class); }
    public function gpu(): BelongsTo        { return $this->belongsTo(Gpu::class); }
    public function motherboard(): BelongsTo { return $this->belongsTo(Motherboard::class); }
    public function ram(): BelongsTo        { return $this->belongsTo(Ram::class); }
    public function ssd(): BelongsTo        { return $this->belongsTo(Ssd::class); }
    public function psu(): BelongsTo        { return $this->belongsTo(Psu::class); }
    public function game(): BelongsTo       { return $this->belongsTo(Game::class); }
}
