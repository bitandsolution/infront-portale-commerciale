<?php

namespace App\Models;

use App\Domain\Proposals\Exceptions\ProposalImmutable;
use App\Enums\ProposalEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Entry of the history of a proposal (M07 F-10, CA 6; D-199): only inserts (model and triggers),
 * visible with the permissions of the proposal, distinct from the technical audit (admin only).
 * Removed only with the deletion of a draft (cascade).
 *
 * @property int $id
 * @property int $proposal_id
 * @property int|null $proposal_version_id
 * @property ProposalEventType $type
 * @property int $user_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Proposal $proposal
 * @property-read User $user
 */
final class ProposalEvent extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProposalEventType::class,
        ];
    }

    protected static function booted(): void
    {
        self::updating(fn (): never => throw ProposalImmutable::history());
        self::deleting(fn (): never => throw ProposalImmutable::history());
    }

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<ProposalVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'proposal_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
