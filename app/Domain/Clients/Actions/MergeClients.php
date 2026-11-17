<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Clients\Data\ClientsAuditValues;
use App\Domain\Clients\Exceptions\ClientsActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\ProposalEventType;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ProposalGroup;
use App\Models\User;
use App\Support\Text;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Merge of two duplicate clients by the admin (M05 F-08; D-359): the client chosen to stay keeps its
 * own data and owner; the duplicate becomes not active, points to it with merged_into_id and is never
 * deleted (C4). From then on the duplicate is read-only and cannot be reactivated or reassigned.
 *
 * - The client that stays must be another client, active and not merged itself.
 * - Clients already merged into the duplicate are pointed to the client that stays, so every merged
 *   client points directly to a client that exists (one event client.merged for each of them).
 * - Both rows are locked in id order (D-216), so two merges at the same time cannot cross.
 *
 * Proposals (F5-07; D-480…D-484): every group of the duplicate (and of the clients already merged into
 * it) moves to the client that stays with its variants, in any status; the agent does not change, the
 * frozen versions and the PDF stay as they are. Locks: clients, then groups, then variants, in id
 * order, up to 3 attempts; lock_version of every moved variant grows, so an open configurator gets the
 * conflict and reloads. Audit proposal.client_merged per group (on the variant A), history
 * "client_merged" per variant, moved_groups in client.merged; no e-mail (D-478).
 */
final class MergeClients
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Client $duplicate, Client $keep, User $actor): Client
    {
        if ($duplicate->id === $keep->id) {
            throw ClientsActionNotAllowed::mergeIntoItself();
        }

        return DB::transaction(function () use ($duplicate, $keep, $actor): Client {
            $locked = Client::query()
                ->whereKey([$duplicate->id, $keep->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $duplicate = $locked->get($duplicate->id) ?? throw (new ModelNotFoundException)->setModel(Client::class, [$duplicate->id]);
            $keep = $locked->get($keep->id) ?? throw (new ModelNotFoundException)->setModel(Client::class, [$keep->id]);

            if ($duplicate->isMerged()) {
                throw ClientsActionNotAllowed::merged($duplicate->company_name);
            }

            if ($keep->isMerged() || ! $keep->is_active) {
                throw ClientsActionNotAllowed::mergeTargetNotActive($keep->company_name);
            }

            $this->pointTo($duplicate, $keep, $actor);

            Client::query()
                ->where('merged_into_id', $duplicate->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(fn (Client $earlier) => $this->pointTo($earlier, $keep, $actor));

            return $keep;
        }, 3);
    }

    private function pointTo(Client $client, Client $keep, User $actor): void
    {
        $before = ClientsAuditValues::status($client);
        $moved = $this->moveProposals($client, $keep, $actor);

        $client->is_active = false;
        $client->merged_into_id = $keep->id;
        $client->setRelation('mergedInto', $keep);
        $client->save();

        [$old, $new] = AuditLog::changes($before, ClientsAuditValues::status($client));

        if ($moved > 0) {
            $new['moved_groups'] = $moved;
        }

        $this->audit->record(AuditAction::ClientMerged, $actor, AuditSubject::client($client), $old, $new);
    }

    /**
     * Moves the groups of a client and their variants to the client that stays (D-480, D-482, D-484).
     *
     * @return int the number of groups moved
     */
    private function moveProposals(Client $from, Client $to, User $actor): int
    {
        $groups = ProposalGroup::query()
            ->where('client_id', $from->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($groups as $group) {
            $variants = Proposal::query()
                ->where('proposal_group_id', $group->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $group->forceFill(['client_id' => $to->id])->save();

            foreach ($variants as $variant) {
                $variant->forceFill([
                    'client_id' => $to->id,
                    'lock_version' => $variant->lock_version + 1,
                ])->save();

                ProposalEvent::query()->create([
                    'proposal_id' => $variant->id,
                    'proposal_version_id' => $variant->current_version_id,
                    'type' => ProposalEventType::ClientMerged,
                    'user_id' => $actor->id,
                    'notes' => Text::get('proposals.event_notes.client_merged', ['from' => $from->company_name, 'to' => $to->company_name]),
                ]);
            }

            $sorted = $variants->sortBy('variant_letter');
            $first = $sorted->first();

            if ($first instanceof Proposal) {
                $this->audit->record(
                    AuditAction::ProposalClientMerged,
                    $actor,
                    AuditSubject::proposal($first),
                    ['client_id' => $from->id, 'client_name' => $from->company_name],
                    [
                        'client_id' => $to->id,
                        'client_name' => $to->company_name,
                        'proposal_group_id' => $group->id,
                        'variant_letters' => $sorted->pluck('variant_letter')->implode(', '),
                    ],
                );
            }
        }

        return $groups->count();
    }
}
