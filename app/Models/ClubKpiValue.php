<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Value of a KPI of a club in a season (M08 §5, F-05; D-203, D-491): integer ≥ 0 with its source and
 * reference period; a missing value has no row. Written only by SetClubKpis, which moves updated_at
 * and updated_by only when value, source or period change: an untouched value keeps its date, so the
 * warning of F-08 stays true (D-492).
 *
 * @property int $id
 * @property int $club_id
 * @property int $season_id
 * @property int $kpi_definition_id
 * @property int $value
 * @property string $source
 * @property string $reference_period
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read KpiDefinition $definition
 * @property-read User|null $updater
 */
final class ClubKpiValue extends Model
{
    /** M08 F-08, D-492: a value not updated for more than 6 months is shown with a warning. */
    public const STALE_MONTHS = 6;

    protected $guarded = ['id'];

    public function isStale(?Carbon $now = null): bool
    {
        return $this->updated_at->lt(($now ?? Carbon::now())->copy()->subMonthsNoOverflow(self::STALE_MONTHS));
    }

    /**
     * @return BelongsTo<KpiDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(KpiDefinition::class, 'kpi_definition_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }
}
