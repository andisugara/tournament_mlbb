<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionSetup extends Model
{
    protected $fillable = [
        'name', 
        'total_teams', 
        'teams_advance_to_playoff', 
        'upper_bracket_direct_seed',
        'regular_season_best_of',
        'playoff_upper_best_of',
        'playoff_lower_best_of',
        'playoff_gf_best_of',
        'is_double_round_robin'
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class, 'competition_id');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(TournamentAward::class, 'competition_id');
    }
}
