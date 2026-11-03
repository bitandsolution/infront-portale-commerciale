<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Seasonal fixed price of a club and partnership tier (M03 F-07; D-029): the same at any start date. Never for the free package.
 * Euro VAT excluded, exact decimal string (C5). "Not available" is the absence of the row (D-190).
 *
 * @property int $id
 * @property int $price_list_id
 * @property int $club_id
 * @property int $partnership_tier_id
 * @property string $season_price
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PriceList $priceList
 * @property-read Club $club
 * @property-read PartnershipTier $partnershipTier
 */
final class PartnershipPrice extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'season_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
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
