<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Number of partnership contracts of a tier sellable by a club in a season (M04 F-02; D-048, D-052); never for the free package.
 * is_enabled off = not managed (not sellable), 0 with the flag on = sold out (D-066, D-191).
 *
 * @property int $id
 * @property int $season_id
 * @property int $club_id
 * @property int $partnership_tier_id
 * @property bool $is_enabled
 * @property int $quantity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Season $season
 * @property-read Club $club
 * @property-read PartnershipTier $partnershipTier
 */
final class PartnershipCapacity extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'quantity' => 'integer',
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
     * @return BelongsTo<PartnershipTier, $this>
     */
    public function partnershipTier(): BelongsTo
    {
        return $this->belongsTo(PartnershipTier::class);
    }
}
