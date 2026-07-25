<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stage extends Model
{
    protected $fillable = ['competition_id', 'type', 'format', 'seeding_rule'];

    protected $casts = [
        'seeding_rule' => 'array',
    ];

    public function competitionSetup(): BelongsTo
    {
        return $this->belongsTo(CompetitionSetup::class, 'competition_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MlMatch::class, 'stage_id');
    }
}
