<?php

namespace App\Models;

use App\Enums\MatchdayStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Matchday of an edition (D-186), e.g. regular stage, matchday 7. Created when the first match of
 * that matchday is entered or imported.
 *
 * @property int $id
 * @property int $competition_season_id
 * @property MatchdayStage $stage
 * @property int $number
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CompetitionSeason $competitionSeason
 */
final class Matchday extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => MatchdayStage::class,
            'number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CompetitionSeason, $this>
     */
    public function competitionSeason(): BelongsTo
    {
        return $this->belongsTo(CompetitionSeason::class);
    }

    /**
     * @return HasMany<FixtureMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(FixtureMatch::class);
    }
}
