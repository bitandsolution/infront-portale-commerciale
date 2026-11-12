<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\CommitmentReleaseReason;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\InventoryCommitment;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ProposalGroup;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

/**
 * Choice of the client among the approved variants of a group (D-396, D-410; DM-03b), recorded by
 * admin or manager, once: the chosen variant stays "Approvata", the others become "Non scelta" and
 * their open commitments are released with the reason "not_chosen" (D-192: a release, never a
 * change). Only on a group approved with two or more variants; a wrong choice is corrected with the
 * cancellation of F5. Group and variants locked in order of id like the approval; history
 * "client_chosen" and audit proposal.client_chosen on every variant.
 */
final class RecordClientChoice
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(ProposalGroup $group, int $chosenId, User $actor): Proposal
    {
        return DB::transaction(function () use ($group, $chosenId, $actor): Proposal {
            ProposalGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $variants = Proposal::query()
                ->where('proposal_group_id', $group->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($variants->count() < 2 || $variants->contains(fn (Proposal $variant): bool => $variant->status !== ProposalStatus::Approved)) {
                throw ProposalsActionNotAllowed::groupNotApproved();
            }

            $chosen = $variants->firstWhere('id', $chosenId);

            if (! $chosen instanceof Proposal) {
                throw ProposalsActionNotAllowed::variantNotInGroup();
            }

            $now = now();

            foreach ($variants as $variant) {
                $isChosen = $variant->id === $chosen->id;
                $released = 0;

                if (! $isChosen) {
                    $released = InventoryCommitment::query()
                        ->where('proposal_id', $variant->id)
                        ->whereNull('released_at')
                        ->update([
                            'released_at' => $now,
                            'released_by' => $actor->id,
                            'release_reason' => CommitmentReleaseReason::NotChosen->value,
                            'updated_at' => $now,
                        ]);

                    $variant->forceFill([
                        'status' => ProposalStatus::NotChosen,
                        'lock_version' => $variant->lock_version + 1,
                    ])->save();
                }

                ProposalEvent::query()->create([
                    'proposal_id' => $variant->id,
                    'proposal_version_id' => $variant->current_version_id,
                    'type' => ProposalEventType::ClientChosen,
                    'user_id' => $actor->id,
                    'notes' => $isChosen
                        ? Text::get('proposals.event_notes.chosen')
                        : Text::get('proposals.event_notes.not_chosen', ['letter' => (string) $chosen->variant_letter]),
                ]);

                $this->audit->record(
                    AuditAction::ProposalClientChosen,
                    $actor,
                    AuditSubject::proposal($variant),
                    ['status' => ProposalStatus::Approved->value],
                    [
                        'status' => $isChosen ? ProposalStatus::Approved->value : ProposalStatus::NotChosen->value,
                        'proposal_group_id' => $group->id,
                        'variant_letter' => $variant->variant_letter,
                        'chosen_variant' => $chosen->variant_letter,
                        'released_commitments' => $released,
                    ],
                );
            }

            return $chosen;
        }, 3);
    }
}
