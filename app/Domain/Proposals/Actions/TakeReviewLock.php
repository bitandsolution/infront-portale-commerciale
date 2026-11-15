<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\ReviewLock;
use App\Enums\AuditAction;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lock of the work on a group waiting for approval (D-216, D-446; M07 F-09; F5-01):
 * - handle(): admin or manager open the detail of the group. A free (or expired) lock is taken, with
 *   the audit event proposal.review_lock_taken; the lock of the same user is renewed without an
 *   event (D-450, D-451); the lock of another user is left as it is (the page is read only).
 * - takeOver(): the admin takes the lock of another user, after a confirmation (D-446), with the
 *   event proposal.review_lock_taken_over and the name of the previous holder.
 * Group and variants are locked like the other Actions of the life cycle (D-216).
 */
final class TakeReviewLock
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(ProposalGroup $group, User $actor): void
    {
        DB::transaction(function () use ($group, $actor): void {
            $variants = $this->lockedVariants($group);

            if ($variants === null) {
                return;
            }

            $holder = ReviewLock::holderId($variants);

            if ($holder !== null && $holder !== $actor->id) {
                return;
            }

            ReviewLock::write($variants, $actor, now());

            if ($holder === null) {
                $this->record(AuditAction::ProposalReviewLockTaken, $variants, $actor, null);
            }
        });
    }

    public function takeOver(ProposalGroup $group, User $admin): void
    {
        DB::transaction(function () use ($group, $admin): void {
            $variants = $this->lockedVariants($group) ?? throw ProposalsActionNotAllowed::groupNotSubmitted();
            $holder = ReviewLock::holderId($variants);

            if ($holder === $admin->id) {
                ReviewLock::write($variants, $admin, now());

                return;
            }

            ReviewLock::write($variants, $admin, now());
            $this->record(
                $holder !== null ? AuditAction::ProposalReviewLockTakenOver : AuditAction::ProposalReviewLockTaken,
                $variants,
                $admin,
                $holder !== null ? ReviewLock::name($holder) : null,
            );
        });
    }

    /**
     * The variants of the group, locked, when every one waits for approval; null otherwise.
     *
     * @return Collection<int, Proposal>|null
     */
    private function lockedVariants(ProposalGroup $group): ?Collection
    {
        ProposalGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
        $variants = Proposal::query()
            ->where('proposal_group_id', $group->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($variants->isEmpty() || $variants->contains(fn (Proposal $variant): bool => $variant->status !== ProposalStatus::Submitted)) {
            return null;
        }

        return $variants;
    }

    /**
     * One event per group, on its first variant (like the slides of the document, DM-05).
     *
     * @param  Collection<int, Proposal>  $variants
     */
    private function record(AuditAction $action, Collection $variants, User $actor, ?string $previous): void
    {
        /** @var Proposal $first */
        $first = $variants->sortBy('variant_letter')->first();

        $this->audit->record(
            $action,
            $actor,
            AuditSubject::proposal($first),
            $previous !== null ? ['review_locked_by' => $previous] : [],
            ['review_locked_by' => $actor->first_name.' '.$actor->last_name, 'proposal_group_id' => $first->proposal_group_id],
        );
    }
}
