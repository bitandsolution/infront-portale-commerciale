<?php

namespace App\Models;

use App\Enums\CompetitionType;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Competition (M02 F-02; D-012, D-185): a league or a cup. Every Serie C group is a competition of
 * its own; the tier (1 = A, 2 = B, 3 = C) serves the slide condition "competition" (M08 §4.2).
 * The top match KPI exists only where has_top_match_kpi is set: today only Serie A (D-036).
 *
 * @property int $id
 * @property int $discipline_id
 * @property string $code
 * @property string $name
 * @property string $short_name
 * @property CompetitionType $type
 * @property int $tier
 * @property bool $has_top_match_kpi
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Discipline $discipline
 */
final class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CompetitionType::class,
            'tier' => 'integer',
            'has_top_match_kpi' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
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
