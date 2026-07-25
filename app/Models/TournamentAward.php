<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentAward extends Model
{
    protected $table = 'tournament_awards';

    protected $fillable = [
        'competition_id',
        'award_type',
        'player_id',
        'avg_rating',
    ];

    protected $casts = [
        'avg_rating' => 'double',
    ];

    public function competitionSetup(): BelongsTo
    {
        return $this->belongsTo(CompetitionSetup::class, 'competition_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
