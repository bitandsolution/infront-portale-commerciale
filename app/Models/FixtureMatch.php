<?php

namespace App\Models;

use App\Enums\MatchSource;
use App\Enums\MatchStatus;
use Database\Factories\FixtureMatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Match of a calendar (M02 F-08; D-185). Named FixtureMatch because "match" is a reserved word of
 * PHP. Natural key: matchday + home club + away club (F-10). Date and kick-off time are local sports
 * values without conversion (C7); the kick-off time is often unknown until shortly before. Cancelled,
 * never deleted (D-319).
 *
 * @property int $id
 * @property int $matchday_id
 * @property int $home_club_id
 * @property int $away_club_id
 * @property int|null $stadium_id
 * @property Carbon $match_date
 * @property string|null $kickoff_time
 * @property MatchStatus $status
 * @property MatchSource $source
 * @property int|null $last_import_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Matchday $matchday
 * @property-read Club $homeClub
 * @property-read Club $awayClub
 * @property-read Stadium|null $stadium
 */
final class FixtureMatch extends Model
{
    /** @use HasFactory<FixtureMatchFactory> */
    use HasFactory;

    protected $table = 'matches';

    protected $guarded = ['id'];

    /**
     * Same defaults as the columns, so a new match has a status before it is saved (audit values).
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'scheduled',
        'source' => 'manual',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_date' => 'date',
            'status' => MatchStatus::class,
            'source' => MatchSource::class,
        ];
    }

    /**
     * Kick-off time as HH:MM, or null.
     */
    public function kickoff(): ?string
    {
        return $this->kickoff_time !== null ? substr($this->kickoff_time, 0, 5) : null;
    }

    /**
     * Readable name, e.g. "Alfa – Beta, 7ª giornata, 18/10/2027".
     */
    public function label(): string
    {
        return $this->homeClub->short_name.' – '.$this->awayClub->short_name.', '.$this->matchday->number.'ª giornata, '.$this->match_date->format('d/m/Y');
    }

    /**
     * @return BelongsTo<Matchday, $this>
     */
    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function homeClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'home_club_id');
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function awayClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'away_club_id');
    }

    /**
     * @return BelongsTo<Stadium, $this>
     */
    public function stadium(): BelongsTo
    {
        return $this->belongsTo(Stadium::class);
    }
}
