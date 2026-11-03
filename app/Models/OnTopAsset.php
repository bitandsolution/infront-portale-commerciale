<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * On-top text of a price list (M03 F-10; D-049, D-335): branded content, match sponsor,
 * activations; only descriptive, never a price. For a club, or general when club_id is null.
 *
 * @property int $id
 * @property int $price_list_id
 * @property int|null $club_id
 * @property string $title
 * @property string $body
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PriceList $priceList
 * @property-read Club|null $club
 */
final class OnTopAsset extends Model
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
}
