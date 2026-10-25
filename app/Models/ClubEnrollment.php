<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Seasonal enrollment of a club in an edition (M02 F-06, D-185): it handles promotions,
 * relegations and Serie C groups. One league per club and season, checked by EnrollClubs.
 *
 * @property int $id
 * @property int $competition_season_id
 * @property int $club_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CompetitionSeason $competitionSeason
 * @property-read Club $club
 */
final class ClubEnrollment extends Model
{
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<CompetitionSeason, $this>
     */
    public function competitionSeason(): BelongsTo
    {
        return $this->belongsTo(CompetitionSeason::class);
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
