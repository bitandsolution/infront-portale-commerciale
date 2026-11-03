<?php

namespace App\Models;

use App\Enums\CommitmentReleaseReason;
use App\Enums\InventoryResource;
use Database\Factories\InventoryCommitmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Commitment of the inventory by an approved proposal (M04 §7; D-062, D-192, D-334): club, resource,
 * quantity and contract period, never changed (triggers): a new version releases the old
 * commitments and writes new ones; a cancellation releases them. Residual = capacity − open
 * commitments (released_at null) of the same club, season and resource.
 *
 * The proposal columns have no foreign keys until F5 (D-334): in F2 commitments are written only by
 * the tests, with the factory.
 *
 * @property int $id
 * @property int $season_id
 * @property int $club_id
 * @property int $proposal_id
 * @property int $proposal_version_id
 * @property int $proposal_version_club_id
 * @property InventoryResource $resource_type
 * @property int|null $inventory_pool_id
 * @property int|null $partnership_tier_id
 * @property int|null $hospitality_variant_id
 * @property int $quantity
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property bool $is_forced
 * @property string|null $forced_reason
 * @property int $created_by
 * @property Carbon|null $released_at
 * @property int|null $released_by
 * @property CommitmentReleaseReason|null $release_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Season $season
 * @property-read Club $club
 */
final class InventoryCommitment extends Model
{
    /** @use HasFactory<InventoryCommitmentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resource_type' => InventoryResource::class,
            'quantity' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_forced' => 'boolean',
            'released_at' => 'datetime',
            'release_reason' => CommitmentReleaseReason::class,
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
}
