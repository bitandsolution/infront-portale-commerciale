<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Price per seat of a club and hospitality variant for the season (M03 F-09; D-055): seats × price per seat in the configurator.
 * Euro VAT excluded, exact decimal string (C5). "Not available" is the absence of the row (D-190).
 *
 * @property int $id
 * @property int $price_list_id
 * @property int $club_id
 * @property int $hospitality_variant_id
 * @property string $seat_price
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PriceList $priceList
 * @property-read Club $club
 * @property-read HospitalityVariant $hospitalityVariant
 */
final class HospitalityPrice extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seat_price' => 'decimal:2',
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
     * @return BelongsTo<HospitalityVariant, $this>
     */
    public function hospitalityVariant(): BelongsTo
    {
        return $this->belongsTo(HospitalityVariant::class);
    }
}
