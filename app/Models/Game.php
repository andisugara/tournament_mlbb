<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = ['match_id', 'game_number', 'winner_team_id', 'duration_seconds'];

    public function match(): BelongsTo
    {
        return $this->belongsTo(MlMatch::class, 'match_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function playerStats(): HasMany
    {
        return $this->hasMany(PlayerGameStat::class);
    }
}
