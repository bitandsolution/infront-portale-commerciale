<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Capacity of a LED pool for a club in a season (M04 F-01; D-062, D-066): seconds per match (C6), multiples of the LED step of the settings.
 * is_enabled off = not managed (not sellable), 0 with the flag on = sold out (D-066, D-191).
 *
 * @property int $id
 * @property int $season_id
 * @property int $club_id
 * @property int $inventory_pool_id
 * @property bool $is_enabled
 * @property int $seconds_per_match
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Season $season
 * @property-read Club $club
 * @property-read InventoryPool $inventoryPool
 */
final class PoolCapacity extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'seconds_per_match' => 'integer',
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
     * @return BelongsTo<InventoryPool, $this>
     */
    public function inventoryPool(): BelongsTo
    {
        return $this->belongsTo(InventoryPool::class);
    }
}
