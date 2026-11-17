<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\GroupInventory;
use App\Domain\Proposals\Services\ProposalNotifications;
use App\Domain\Settings\Services\Settings;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\CommitmentReleaseReason;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ProposalGroup;
use App\Models\ProposalVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancellation of an approved group by the admin (M07 F-07, FL-05, RB-01, CA8; D-031, D-095, D-444,
 * D-447, D-469…D-473; F5-05), in one transaction repeated up to 3 times on a deadlock (D-216):
 *
 * - group and variants locked in order of id, like the approval, the client choice and the new
 *   version, so that they wait for each other;
 * - every **approved** variant becomes "Annullata" with date, author and reason (required, at least
 *   forced_reason_min_length characters); the "not chosen" variants stay so (D-444, D-473);
 * - the open commitments of those variants are released with the reason "cancelled" (D-192: a
 *   release, never a change); the capacities need no lock, a release only frees residuals;
 * - the working copy of a new version in progress is deleted with its rows (D-469);
 * - history "cancelled" with the reason and audit proposal.cancelled on every cancelled variant.
 *
 * The cancellation is final (D-471): nothing brings a variant back to "Approvata". The PDF already
 * generated is not changed (D-447, D-470). One e-mail to the agent with the reason (F5-06, D-478).
 */
final class CancelGroup
{
    public function __construct(
        private readonly GroupInventory $inventory,
        private readonly AuditLog $audit,
        private readonly Settings $settings,
        private readonly ProposalNotifications $notifications,
    ) {}

    /**
     * @return list<Proposal> the variants cancelled
     */
    public function handle(ProposalGroup $group, string $reason, User $actor): array
    {
        if (! Ability::ProposalsManageApproved->allows($actor->role)) {
            throw ProposalsActionNotAllowed::cancelNotAllowed();
        }

        $reason = trim($reason);
        $min = $this->settings->forcedReasonMinLength();

        if (mb_strlen($reason) < $min) {
            throw ProposalsActionNotAllowed::cancelReasonTooShort($min);
        }

        return DB::transaction(function () use ($group, $reason, $actor): array {
            ProposalGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $variants = Proposal::query()
                ->where('proposal_group_id', $group->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $approved = $variants->filter(fn (Proposal $variant): bool => $variant->status === ProposalStatus::Approved)->values();

            if ($approved->isEmpty()) {
                throw ProposalsActionNotAllowed::groupNotCancellable();
            }

            $now = now();
            $cancelled = [];

            foreach ($approved as $variant) {
                $working = $variant->workingVersion()->lockForUpdate()->first();
                $discardedWork = false;

                if ($working instanceof ProposalVersion) {
                    $working->delete();
                    $discardedWork = true;
                }

                $released = $this->inventory->release($variant, $actor, CommitmentReleaseReason::Cancelled);

                $variant->forceFill([
                    'status' => ProposalStatus::Cancelled,
                    'cancelled_at' => $now,
                    'cancelled_by' => $actor->id,
                    'cancel_reason' => $reason,
                    'lock_version' => $variant->lock_version + 1,
                ])->save();

                ProposalEvent::query()->create([
                    'proposal_id' => $variant->id,
                    'proposal_version_id' => $variant->current_version_id,
                    'type' => ProposalEventType::Cancelled,
                    'user_id' => $actor->id,
                    'notes' => $reason,
                ]);

                $this->audit->record(
                    AuditAction::ProposalCancelled,
                    $actor,
                    AuditSubject::proposal($variant),
                    ['status' => ProposalStatus::Approved->value],
                    [
                        'status' => ProposalStatus::Cancelled->value,
                        'proposal_group_id' => $group->id,
                        'variant_letter' => $variant->variant_letter,
                        'version' => $variant->currentVersion()->value('number'),
                        'cancel_reason' => $reason,
                        'released_commitments' => $released,
                        'discarded_revision' => $discardedWork,
                    ],
                );

                $cancelled[] = $variant;
            }

            $this->notifications->cancelled($group, $actor, $reason, $cancelled);

            return $cancelled;
        }, 3);
    }
}
