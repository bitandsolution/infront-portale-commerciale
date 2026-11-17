<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\ProposalVersion;
use App\Models\User;
use App\Support\Amounts;
use Illuminate\Support\Facades\DB;

/**
 * "Annulla le modifiche" of an approved variant (D-466; F5-04): the working copy made by StartRevision
 * is deleted with its rows and seats (cascade, C3; a working version is not frozen, so the triggers
 * allow it) and no version is born; the variant stays at its current frozen version, its commitments
 * untouched. Audit proposal.revision_discarded with the version kept and, when the copy had changed it,
 * the list total that is lost.
 */
final class DiscardRevision
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Proposal $proposal, User $actor): void
    {
        if (! Ability::ProposalsManageApproved->allows($actor->role)) {
            throw ProposalsActionNotAllowed::notRevisable($proposal->number);
        }

        DB::transaction(function () use ($proposal, $actor): void {
            if ($proposal->proposal_group_id !== null) {
                ProposalGroup::query()->whereKey($proposal->proposal_group_id)->lockForUpdate()->first();
                Proposal::query()->where('proposal_group_id', $proposal->proposal_group_id)->orderBy('id')->lockForUpdate()->get(['id']);
            }

            /** @var Proposal $locked */
            $locked = Proposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            $working = $locked->workingVersion()->lockForUpdate()->first();

            if ($locked->status !== ProposalStatus::Approved || ! $working instanceof ProposalVersion) {
                throw ProposalsActionNotAllowed::notUnderRevision($locked->number);
            }

            $current = $locked->currentVersion()->first();
            $listTotal = $working->list_total;
            $working->delete();
            $locked->forceFill(['lock_version' => $locked->lock_version + 1])->save();

            // The list total of the copy only when it differs from the current version (changes lost).
            $changed = $current === null || Amounts::normalize($listTotal) !== Amounts::normalize($current->list_total);

            $this->audit->record(
                AuditAction::ProposalRevisionDiscarded,
                $actor,
                AuditSubject::proposal($locked),
                $changed ? ['list_total' => $listTotal] : [],
                [
                    'proposal_group_id' => $locked->proposal_group_id,
                    'variant_letter' => $locked->variant_letter,
                    'version' => $current?->number,
                    'list_total' => $current?->list_total,
                ],
            );
        });
    }
}
