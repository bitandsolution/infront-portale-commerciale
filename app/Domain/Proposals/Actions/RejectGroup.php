<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ProposalGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rejection of a group of variants by admin or manager (M07 §4, FL-03; D-099, D-395, D-412; DM-03a):
 * every variant of the group, all "In approvazione", becomes "Rifiutata" with the optional reason;
 * the agent corrects and sends the group again. Group and variants are locked like SubmitGroup, so a
 * second click or a decision taken meanwhile is refused (groupNotSubmitted). History "rejected" with
 * the reason, audit proposal.rejected per variant. The e-mail to the agent arrives with F5.
 */
final class RejectGroup
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(ProposalGroup $group, ?string $reason, User $actor): void
    {
        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

        DB::transaction(function () use ($group, $reason, $actor): void {
            ProposalGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $variants = Proposal::query()
                ->where('proposal_group_id', $group->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('workingVersion')
                ->get();

            if ($variants->isEmpty() || $variants->contains(fn (Proposal $variant): bool => $variant->status !== ProposalStatus::Submitted)) {
                throw ProposalsActionNotAllowed::groupNotSubmitted();
            }

            $now = now();

            foreach ($variants as $variant) {
                $variant->forceFill([
                    'status' => ProposalStatus::Rejected,
                    'decided_at' => $now,
                    'decided_by' => $actor->id,
                    'last_rejection_reason' => $reason,
                    'lock_version' => $variant->lock_version + 1,
                ])->save();

                ProposalEvent::query()->create([
                    'proposal_id' => $variant->id,
                    'proposal_version_id' => $variant->workingVersion?->id,
                    'type' => ProposalEventType::Rejected,
                    'user_id' => $actor->id,
                    'notes' => $reason,
                ]);

                $this->audit->record(
                    AuditAction::ProposalRejected,
                    $actor,
                    AuditSubject::proposal($variant),
                    ['status' => ProposalStatus::Submitted->value],
                    ['status' => ProposalStatus::Rejected->value, 'proposal_group_id' => $group->id, 'variant_letter' => $variant->variant_letter],
                    $reason,
                );
            }
        });
    }
}
