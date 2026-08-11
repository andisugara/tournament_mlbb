<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerGameStat extends Model
{
    protected $table = 'player_game_stats';

    protected $fillable = [
        'game_id',
        'player_id',
        'role',
        'hero',
        'kills',
        'deaths',
        'assists',
        'gold_earned',
        'is_mvp',
        'rating',
    ];

    protected $casts = [
        'is_mvp' => 'boolean',
        'rating' => 'double',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
