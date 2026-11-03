<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hospitality seats of a variant available at a club in a season (M04 F-03; D-053).
 * is_enabled off = not managed (not sellable), 0 with the flag on = sold out (D-066, D-191).
 *
 * @property int $id
 * @property int $season_id
 * @property int $club_id
 * @property int $hospitality_variant_id
 * @property bool $is_enabled
 * @property int $seats
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Season $season
 * @property-read Club $club
 * @property-read HospitalityVariant $hospitalityVariant
 */
final class HospitalityCapacity extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'seats' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<HospitalityVariant, $this>
     */
    public function hospitalityVariant(): BelongsTo
    {
        return $this->belongsTo(HospitalityVariant::class);
    }
}
