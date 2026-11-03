<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Benefit text of a club, partnership tier and category in a price list (M03 F-08; D-117, D-335):
 * optional, kept for the future web landing; in the documents the benefits are static slides.
 * One text per club, tier and category; an empty text is no row.
 *
 * @property int $id
 * @property int $price_list_id
 * @property int $club_id
 * @property int $partnership_tier_id
 * @property int $benefit_category_id
 * @property string $body
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PriceList $priceList
 * @property-read Club $club
 * @property-read PartnershipTier $partnershipTier
 * @property-read BenefitCategory $benefitCategory
 */
final class PartnershipBenefit extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
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

    /**
     * @return BelongsTo<BenefitCategory, $this>
     */
    public function benefitCategory(): BelongsTo
    {
        return $this->belongsTo(BenefitCategory::class);
    }
}
