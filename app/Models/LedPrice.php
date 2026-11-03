<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * LED price of a club and format in a price list (M03 F-05; D-054, D-057, D-190): the cost for 1′
 * per match, euro VAT excluded, 2 decimals, as an exact decimal string (C5: never a float).
 * "Not available" is the absence of the row.
 *
 * @property int $id
 * @property int $price_list_id
 * @property int $club_id
 * @property int $led_format_id
 * @property string $unit_price
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PriceList $priceList
 * @property-read Club $club
 * @property-read LedFormat $ledFormat
 */
final class LedPrice extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
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
     * @return BelongsTo<LedFormat, $this>
     */
    public function ledFormat(): BelongsTo
    {
        return $this->belongsTo(LedFormat::class);
    }
}
