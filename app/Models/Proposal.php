<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use App\Models\Concerns\HasOwner;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Proposal (M06, M07; D-198, D-376): header with a progressive number (D-371), the agent who owns it,
 * client, season and status; from DM-01 also a variant (letter A…J) of a group of proposals for the
 * same client and season, presented in one document (D-396; a single proposal is variant A of its group); the rows live in its versions: one working version (number null) and
 * the frozen versions 1, 2… of F5. The agent sees only his own proposals (RB-14): queries go through
 * visibleTo() (HasOwner, column agent_id) and ProposalPolicy. Only a draft is deleted (M07 RB-08),
 * physically, with its working version (cascade).
 *
 * @property int $id
 * @property int $number
 * @property int|null $proposal_group_id
 * @property string|null $variant_letter
 * @property int $agent_id
 * @property int|null $client_id
 * @property int|null $season_id
 * @property ProposalStatus $status
 * @property int|null $current_version_id
 * @property Carbon|null $submitted_at
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property string|null $last_rejection_reason
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancel_reason
 * @property int|null $review_locked_by
 * @property Carbon|null $review_locked_at
 * @property int|null $duplicated_from_id
 * @property int $lock_version
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $agent
 * @property-read Client|null $client
 * @property-read Season|null $season
 * @property-read ProposalVersion|null $workingVersion
 * @property-read ProposalVersion|null $currentVersion
 * @property-read Proposal|null $duplicatedFrom
 * @property-read ProposalGroup|null $group
 */
final class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use HasFactory;

    use HasOwner;

    protected $guarded = ['id'];

    /**
     * Defaults of the columns, also before the first save.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'lock_version' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => ProposalStatus::class,
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'review_locked_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /** The data model keeps agent_id (D-376). */
    public function ownerColumn(): string
    {
        return 'agent_id';
    }

    public function isDraft(): bool
    {
        return $this->status === ProposalStatus::Draft;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Group of variants (D-396).
     *
     * @return BelongsTo<ProposalGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProposalGroup::class, 'proposal_group_id');
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
     * Every version: the working one and the frozen ones.
     *
     * @return HasMany<ProposalVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ProposalVersion::class);
    }

    /**
     * @return HasOne<ProposalVersion, $this>
     */
    public function workingVersion(): HasOne
    {
        return $this->hasOne(ProposalVersion::class)->whereNull('number');
    }

    /**
     * Last frozen version (F5).
     *
     * @return BelongsTo<ProposalVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'current_version_id');
    }

    /**
     * @return HasMany<ProposalEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ProposalEvent::class);
    }

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicated_from_id');
    }
}
