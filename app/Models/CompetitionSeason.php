<?php

namespace App\Models;

use Database\Factories\CompetitionSeasonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Edition of a competition in a season (D-185), e.g. Serie A 2027-28, with the standard number of
 * home matches used while the calendar is not loaded (D-034, D-187). calendar_loaded_at is set by
 * the calendar import (slice F1-04).
 *
 * @property int $id
 * @property int $competition_id
 * @property int $season_id
 * @property int $standard_home_matches
 * @property Carbon|null $calendar_loaded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Competition $competition
 * @property-read Season $season
 */
final class CompetitionSeason extends Model
{
    /** @use HasFactory<CompetitionSeasonFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'standard_home_matches' => 'integer',
            'calendar_loaded_at' => 'datetime',
        ];
    }

    /**
     * Readable name, e.g. "Serie A 2027-28".
     */
    public function label(): string
    {
        return $this->competition->name.' '.$this->season->code;
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
