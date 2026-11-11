<?php

namespace App\Models;

use App\Models\Concerns\HasOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Group of variants of a proposal (D-396; slice DM): the proposals A…J of one agent for the same
 * client and the same season, presented in one document. Every variant is a complete proposal with
 * its own number, dates, clubs and total; a single proposal is a group with one variant. Client and
 * season of the group follow those of its variants (SaveDraft keeps them aligned). The agent sees
 * only his own groups (HasOwner, column agent_id).
 *
 * @property int $id
 * @property int $agent_id
 * @property int|null $client_id
 * @property int|null $season_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $agent
 * @property-read Client|null $client
 * @property-read Season|null $season
 */
final class ProposalGroup extends Model
{
    use HasOwner;

    protected $guarded = ['id'];

    /** Same owner column as the proposals (D-376). */
    public function ownerColumn(): string
    {
        return 'agent_id';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * The variants, in the order of their letter.
     *
     * @return HasMany<Proposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class)->orderBy('variant_letter');
    }
}
