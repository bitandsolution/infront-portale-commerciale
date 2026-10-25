<?php

namespace App\Models;

use App\Enums\SeasonStatus;
use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sports season (M02 F-03; D-013, D-185), e.g. 2027-28. Seasons of the same discipline never
 * overlap (checked by the Actions). The status is set by the admin (D-317): a closed season is
 * read-only for the sports registry.
 *
 * @property int $id
 * @property int $discipline_id
 * @property string $code
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property SeasonStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Discipline $discipline
 */
final class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => SeasonStatus::class,
        ];
    }

    public function isReadOnly(): bool
    {
        return $this->status->isReadOnly();
    }

    /**
     * @return BelongsTo<Discipline, $this>
     */
    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    /**
     * @return HasMany<CompetitionSeason, $this>
     */
    public function competitionSeasons(): HasMany
    {
        return $this->hasMany(CompetitionSeason::class);
    }
}
