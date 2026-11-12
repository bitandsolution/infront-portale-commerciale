<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\DraftEvaluator;
use App\Enums\AuditAction;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ProposalGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Invia in approvazione" of a group of variants (M07 §4; M06 RB-11; D-395, D-396, D-412; DM-03a).
 *
 * - The agent of the group sends all its variants together; every variant must be a draft or a
 *   rejected proposal (D-412).
 * - Every variant is evaluated again on the current list and residuals (DraftEvaluator): with a club
 *   in error, a club without elements, no clubs or no period the sending stops and nothing is written
 *   (ProposalsActionNotAllowed::groupNotReady, with the letters).
 * - The group row, then its proposals in order of id, are locked (D-216): a variant added or deleted
 *   meanwhile, or a second click, is seen.
 * - Every variant becomes "In approvazione" with submitted_at and a new lock_version (an open
 *   configurator then gets the conflict instead of writing); history "submitted", or "resubmitted"
 *   after a rejection (M07-CA6); audit proposal.submitted per variant.
 * The e-mail to admin and manager arrives with F5 (D-395).
 */
final class SubmitGroup
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly DraftEvaluator $evaluator,
    ) {}

    public function handle(ProposalGroup $group, User $agent): void
    {
        DB::transaction(function () use ($group, $agent): void {
            ProposalGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $variants = Proposal::query()
                ->where('proposal_group_id', $group->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->with(['season', 'workingVersion', 'client'])
                ->get()
                ->sortBy('variant_letter')
                ->values();

            if ($variants->isEmpty() || $variants->contains(fn (Proposal $variant): bool => ! $variant->isSubmittable() || ! $variant->isOwnedBy($agent))) {
                throw ProposalsActionNotAllowed::groupNotSubmittable();
            }

            $notReady = [];

            foreach ($variants as $variant) {
                $version = $variant->workingVersion;
                $evaluation = $this->evaluator->evaluateProposal($variant);

                if ($version === null || $version->starts_on === null || $version->ends_on === null
                    || $evaluation === null || $evaluation->blocksSummary() || $evaluation->hasErrors()) {
                    $notReady[] = (string) $variant->variant_letter;
                }
            }

            if ($notReady !== []) {
                throw ProposalsActionNotAllowed::groupNotReady($notReady);
            }

            $now = now();

            foreach ($variants as $variant) {
                $before = $variant->status;
                $variant->forceFill([
                    'status' => ProposalStatus::Submitted,
                    'submitted_at' => $now,
                    'lock_version' => $variant->lock_version + 1,
                ])->save();

                ProposalEvent::query()->create([
                    'proposal_id' => $variant->id,
                    'proposal_version_id' => $variant->workingVersion?->id,
                    'type' => $before === ProposalStatus::Rejected ? ProposalEventType::Resubmitted : ProposalEventType::Submitted,
                    'user_id' => $agent->id,
                ]);

                $this->audit->record(
                    AuditAction::ProposalSubmitted,
                    $agent,
                    AuditSubject::proposal($variant),
                    ['status' => $before->value],
                    ['status' => ProposalStatus::Submitted->value, 'proposal_group_id' => $group->id, 'variant_letter' => $variant->variant_letter],
                );
            }
        });
    }
}
