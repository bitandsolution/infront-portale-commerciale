<?php

namespace App\Models;

use App\Enums\PriceListStatus;
use Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Price list of a season (M03 F-05, F-11; D-190, D-333): one per season, born empty in draft and
 * published once, without a way back. Its prices stay editable after the publication; approved
 * proposals keep the frozen prices (D-041). Read-only when its season is closed (D-333, as D-317).
 *
 * copied_from_id and copy_increase_pct are for the copy with +% of the 1.1 release (D-236).
 *
 * @property int $id
 * @property int $season_id
 * @property PriceListStatus $status
 * @property Carbon|null $published_at
 * @property int|null $published_by
 * @property int|null $copied_from_id
 * @property string|null $copy_increase_pct
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Season $season
 * @property-read User|null $publisher
 * @property-read Collection<int, LedPrice> $ledPrices
 */
final class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * A new price list is a draft (D-333): set here too, so the value is there before saving.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PriceListStatus::class,
            'published_at' => 'datetime',
            'copy_increase_pct' => 'decimal:2',
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === PriceListStatus::Published;
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return HasMany<LedPrice, $this>
     */
    public function ledPrices(): HasMany
    {
        return $this->hasMany(LedPrice::class);
    }
}
