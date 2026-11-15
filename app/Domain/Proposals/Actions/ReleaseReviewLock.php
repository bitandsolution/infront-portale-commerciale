<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Services\ReviewLock;
use App\Enums\AuditAction;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Lascia la lavorazione" (D-452; F5-01): the holder of the lock of a group frees it at once, with the
 * audit event proposal.review_lock_released. Nothing happens when the lock is free, expired or of
 * another user (the button is shown only to the holder; a second click changes nothing).
 */
final class ReleaseReviewLock
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(ProposalGroup $group, User $actor): void
    {
        DB::transaction(function () use ($group, $actor): void {
            ProposalGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $variants = Proposal::query()
                ->where('proposal_group_id', $group->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (ReviewLock::holderId($variants) !== $actor->id) {
                return;
            }

            ReviewLock::write($variants, null, null);
            /** @var Proposal $first */
            $first = $variants->sortBy('variant_letter')->first();

            $this->audit->record(
                AuditAction::ProposalReviewLockReleased,
                $actor,
                AuditSubject::proposal($first),
                ['review_locked_by' => $actor->first_name.' '.$actor->last_name],
                ['review_locked_by' => null, 'proposal_group_id' => $first->proposal_group_id],
            );
        });
    }
}
