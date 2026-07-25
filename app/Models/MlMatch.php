<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MlMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'stage_id',
        'team_a_id',
        'team_b_id',
        'best_of',
        'winner_team_id',
        'bracket_type',
        'round_name',
        'match_code',
        'feeds_win_to_match_id',
        'feeds_win_to_slot',
        'feeds_lose_to_match_id',
        'feeds_lose_to_slot',
        'team_a_source_match_id',
        'team_a_source_type',
        'team_b_source_match_id',
        'team_b_source_type',
        'scheduled_at'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function teamA(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_a_id');
    }

    public function teamB(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_b_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function feedsWinToMatch(): BelongsTo
    {
        return $this->belongsTo(MlMatch::class, 'feeds_win_to_match_id');
    }

    public function feedsLoseToMatch(): BelongsTo
    {
        return $this->belongsTo(MlMatch::class, 'feeds_lose_to_match_id');
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class, 'match_id');
    }
}
