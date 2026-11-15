<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\ReviewLock;
use App\Enums\AuditAction;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ProposalGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Withdrawal of a group waiting for approval by its agent (M07 §4; D-100, D-444; F5-01): every
 * variant goes back to "Bozza" and opens again in the configurator. Refused when admin or manager
 * hold the lock of the work (M07 §9, D-446); an expired lock does not count. Group and variants are
 * locked like SubmitGroup; history "withdrawn" and audit proposal.withdrawn per variant.
 */
final class WithdrawGroup
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(ProposalGroup $group, User $agent): void
    {
        DB::transaction(function () use ($group, $agent): void {
            ProposalGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $variants = Proposal::query()
                ->where('proposal_group_id', $group->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('workingVersion')
                ->get();

            if ($variants->isEmpty() || $variants->contains(fn (Proposal $variant): bool => $variant->status !== ProposalStatus::Submitted || ! $variant->isOwnedBy($agent))) {
                throw ProposalsActionNotAllowed::groupNotSubmitted();
            }

            $holder = ReviewLock::holderId($variants);

            if ($holder !== null) {
                throw ProposalsActionNotAllowed::withdrawLocked(ReviewLock::name($holder));
            }

            foreach ($variants as $variant) {
                $variant->forceFill([
                    'status' => ProposalStatus::Draft,
                    'submitted_at' => null,
                    'review_locked_by' => null,
                    'review_locked_at' => null,
                    'lock_version' => $variant->lock_version + 1,
                ])->save();

                ProposalEvent::query()->create([
                    'proposal_id' => $variant->id,
                    'proposal_version_id' => $variant->workingVersion?->id,
                    'type' => ProposalEventType::Withdrawn,
                    'user_id' => $agent->id,
                ]);

                $this->audit->record(
                    AuditAction::ProposalWithdrawn,
                    $agent,
                    AuditSubject::proposal($variant),
                    ['status' => ProposalStatus::Submitted->value],
                    ['status' => ProposalStatus::Draft->value, 'proposal_group_id' => $group->id, 'variant_letter' => $variant->variant_letter],
                );
            }
        });
    }
}
