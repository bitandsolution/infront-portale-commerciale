<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Data\ProposalsAuditValues;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Enums\AuditAction;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletion of a draft (M07 RB-08, D-372, D-374; C4): physical, with its working version, rows and
 * history in cascade; the trigger proposals_before_delete refuses anything that is not a draft
 * without frozen versions. The number is never reused: the gap is traced by the audit event
 * proposal.draft_deleted, written before the deletion with the header of the draft.
 */
final class DeleteDraft
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Proposal $proposal, User $actor): void
    {
        DB::transaction(function () use ($proposal, $actor): void {
            /** @var Proposal $locked */
            $locked = Proposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw ProposalsActionNotAllowed::notDraft($locked->number);
            }

            $version = $locked->workingVersion;
            $values = $version !== null ? ProposalsAuditValues::draft($locked, $version) : ['number' => $locked->number];

            $this->audit->record(AuditAction::ProposalDraftDeleted, $actor, AuditSubject::proposal($locked), $values, []);

            $locked->delete();
        });
    }
}
