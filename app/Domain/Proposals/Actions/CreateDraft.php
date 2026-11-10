<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Data\ProposalsAuditValues;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\ProposalNumbers;
use App\Domain\Proposals\Services\ProposalPeriod;
use App\Domain\Proposals\Services\ProposalVariants;
use App\Enums\AuditAction;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ProposalVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * New draft (M06 §4 step 1, D-375): it is born when the agent chooses the client, never empty.
 *
 * - The client is an active, not merged client of the agent (M05 F-02, D-366, D-368).
 * - Number from the sequence (D-371), assigned now and never reused.
 * - A new group of variants, with the draft as its variant A (D-396).
 * - Working version with the defaults of the period (D-375): season of the top bar, start = start of
 *   the season or today, end = end of the season. Without a season to choose, season and dates stay
 *   empty and the agent picks them at step 2 (D-381).
 * - History "created" (D-199) and audit proposal.draft_created with the header (D-372).
 */
final class CreateDraft
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly ProposalNumbers $numbers,
        private readonly ProposalPeriod $period,
    ) {}

    public function handle(User $agent, Client $client): Proposal
    {
        if ($agent->role !== Role::Agent || ! $agent->is_active) {
            throw ProposalsActionNotAllowed::notAgent();
        }

        if (! self::selectable($client, $agent)) {
            throw ProposalsActionNotAllowed::clientNotSelectable();
        }

        $season = $this->period->defaultSeason();

        return DB::transaction(function () use ($agent, $client, $season): Proposal {
            $group = ProposalVariants::newGroup($agent, $client, $season);

            $proposal = Proposal::query()->create([
                'number' => $this->numbers->next(),
                'proposal_group_id' => $group->id,
                'variant_letter' => ProposalVariants::FIRST,
                'agent_id' => $agent->id,
                'client_id' => $client->id,
                'season_id' => $season?->id,
                'status' => ProposalStatus::Draft,
            ]);

            $version = ProposalVersion::query()->create([
                'proposal_id' => $proposal->id,
                'starts_on' => $season !== null ? ProposalPeriod::defaultStart($season) : null,
                'ends_on' => $season !== null ? ProposalPeriod::defaultEnd($season) : null,
            ]);

            ProposalEvent::query()->create([
                'proposal_id' => $proposal->id,
                'proposal_version_id' => $version->id,
                'type' => ProposalEventType::Created,
                'user_id' => $agent->id,
            ]);

            $proposal->setRelation('client', $client)->setRelation('season', $season);

            $this->audit->record(
                AuditAction::ProposalDraftCreated,
                $agent,
                AuditSubject::proposal($proposal),
                [],
                ProposalsAuditValues::draft($proposal, $version->refresh()),
            );

            return $proposal;
        });
    }

    /**
     * Whether the agent can choose this client for a proposal (M05 F-02, D-366, D-368).
     */
    public static function selectable(Client $client, User $agent): bool
    {
        return $client->isOwnedBy($agent) && $client->is_active && ! $client->isMerged();
    }
}
