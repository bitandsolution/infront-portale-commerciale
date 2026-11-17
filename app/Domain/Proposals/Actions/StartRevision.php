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
use App\Models\ProposalVersionClub;
use App\Models\ProposalVersionClubSeat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Modifica" of an approved variant by the admin (M07 FL-04, RB-07; D-041, D-096, D-444, D-445,
 * D-466; F5-04): the working version is born as a copy of the current frozen version, rows and seats
 * with the prices frozen then, so the configurator shows at the opening what the current list changes
 * ("da € X a € Y", RB-12, D-464). The copy stays until the admin saves the version n+1
 * (SaveNewVersion) or discards it (DiscardRevision); the agent, the document and the lists keep
 * reading the current frozen version.
 *
 * Group and variants are locked like the other Actions of the life cycle (D-216). When the copy
 * already exists (a second click, another tab) nothing is written. Audit proposal.revision_started; no
 * history event (the history gets "Nuova versione" when the version is saved).
 */
final class StartRevision
{
    /** Columns of a frozen version that a working version never has. */
    private const FROZEN_COLUMNS = [
        'number', 'working_slot', 'frozen_at', 'frozen_by', 'change_note', 'discount_type', 'discount_value',
        'discount_amount', 'final_total', 'is_forced', 'forced_reason', 'snapshot',
    ];

    public function __construct(private readonly AuditLog $audit) {}

    /**
     * @return bool whether the working copy was created now
     */
    public function handle(Proposal $proposal, User $actor): bool
    {
        if (! Ability::ProposalsManageApproved->allows($actor->role)) {
            throw ProposalsActionNotAllowed::notRevisable($proposal->number);
        }

        return DB::transaction(function () use ($proposal, $actor): bool {
            if ($proposal->proposal_group_id !== null) {
                ProposalGroup::query()->whereKey($proposal->proposal_group_id)->lockForUpdate()->first();
                Proposal::query()->where('proposal_group_id', $proposal->proposal_group_id)->orderBy('id')->lockForUpdate()->get(['id']);
            }

            /** @var Proposal $locked */
            $locked = Proposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            $frozen = $locked->currentVersion()->first();

            if ($locked->status !== ProposalStatus::Approved || ! $frozen instanceof ProposalVersion) {
                throw ProposalsActionNotAllowed::notRevisable($locked->number);
            }

            if ($locked->workingVersion()->exists()) {
                return false;
            }

            $working = $frozen->replicate(self::FROZEN_COLUMNS);
            $working->save();

            foreach ($frozen->clubs()->with('seats')->orderBy('sort_order')->orderBy('id')->get() as $row) {
                /** @var ProposalVersionClub $copy */
                $copy = $row->replicate();
                $copy->proposal_version_id = $working->id;
                $copy->save();

                foreach ($row->seats as $seat) {
                    /** @var ProposalVersionClubSeat $seatCopy */
                    $seatCopy = $seat->replicate();
                    $seatCopy->proposal_version_club_id = $copy->id;
                    $seatCopy->save();
                }
            }

            $locked->forceFill(['lock_version' => $locked->lock_version + 1])->save();

            $this->audit->record(
                AuditAction::ProposalRevisionStarted,
                $actor,
                AuditSubject::proposal($locked),
                [],
                [
                    'proposal_group_id' => $locked->proposal_group_id,
                    'variant_letter' => $locked->variant_letter,
                    'version' => $frozen->number,
                ],
            );

            return true;
        });
    }
}
