<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Data\ClubEvaluation;
use App\Domain\Proposals\Data\ProposalDuplicated;
use App\Domain\Proposals\Data\ProposalsAuditValues;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\DraftEvaluator;
use App\Domain\Proposals\Services\DraftRepricing;
use App\Domain\Proposals\Services\DraftRowsWriter;
use App\Domain\Proposals\Services\ProposalNumbers;
use App\Domain\Proposals\Services\ProposalPeriod;
use App\Enums\AuditAction;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Enums\Role;
use App\Enums\SeasonStatus;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ProposalVersion;
use App\Models\Season;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

/**
 * "Duplica proposta" (D-091, M06 §5, §9, M06-CA8; F4-05): a new draft of the agent from one of his
 * proposals, also for another client.
 *
 * - Only the agent's own proposals (RB-14, ProposalPolicy::duplicate); the client is an active, not
 *   merged client of the agent (M05 F-02, D-366, D-368), the same of the original by default.
 * - Configuration from the working version of the original (the last frozen version for the
 *   proposals of F5 without one): clubs, package, LED format and minutes, hospitality seats.
 * - Season and dates recalculated on the current values: the season of the original when it is not
 *   closed (otherwise the default season, D-375, with its default dates); the start never before the
 *   default start (today when the season has already started); the end of the original when still
 *   valid, otherwise the end of the season.
 * - List prices, matches and residuals recalculated now (DraftEvaluator, the evaluation of the
 *   automatic save): every club is copied, also when it is no longer valid; its errors are shown in the
 *   draft as for a reopened draft (M06 §9, D-382), and the clubs to correct are reported.
 * - New number (D-371), duplicated_from_id (D-377), history "duplicated" (D-199) and audit
 *   proposal.draft_duplicated with the header, the clubs, the list total and the KPI (D-372).
 */
final class DuplicateProposal
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly ProposalNumbers $numbers,
        private readonly ProposalPeriod $period,
        private readonly DraftEvaluator $evaluator,
        private readonly DraftRowsWriter $rowsWriter,
        private readonly DraftRepricing $repricing,
    ) {}

    public function handle(Proposal $original, User $agent, Client $client): ProposalDuplicated
    {
        if ($agent->role !== Role::Agent || ! $agent->is_active) {
            throw ProposalsActionNotAllowed::notAgent();
        }

        /** @var ProposalVersion|null $source */
        $source = $original->workingVersion()->first() ?? $original->currentVersion()->first();

        if (! $original->isOwnedBy($agent) || $source === null) {
            throw ProposalsActionNotAllowed::notDuplicable($original->number);
        }

        if (! CreateDraft::selectable($client, $agent)) {
            throw ProposalsActionNotAllowed::clientNotSelectable();
        }

        $season = $this->season($original);
        [$startsOn, $endsOn] = $season !== null ? $this->dates($season, $original->season_id === $season->id ? $source : null) : [null, null];
        $sourceRows = SaveDraft::rows($source);
        $evaluation = $this->evaluator->evaluate($season, $startsOn, $endsOn, SaveDraft::fromRows($sourceRows));

        return DB::transaction(function () use ($original, $agent, $client, $season, $startsOn, $endsOn, $source, $sourceRows, $evaluation): ProposalDuplicated {
            $proposal = Proposal::query()->create([
                'number' => $this->numbers->next(),
                'agent_id' => $agent->id,
                'client_id' => $client->id,
                'season_id' => $season?->id,
                'status' => ProposalStatus::Draft,
                'duplicated_from_id' => $original->id,
            ]);

            $version = ProposalVersion::query()->create([
                'proposal_id' => $proposal->id,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                ...DraftRowsWriter::versionTotals($evaluation),
            ]);

            $this->rowsWriter->write($version, $version->clubs()->get(), $evaluation, $season);

            ProposalEvent::query()->create([
                'proposal_id' => $proposal->id,
                'proposal_version_id' => $version->id,
                'type' => ProposalEventType::Duplicated,
                'user_id' => $agent->id,
                'notes' => Text::get('proposals.history.duplicated_from', ['number' => (string) $original->number]),
            ]);

            $proposal->setRelation('client', $client)->setRelation('season', $season);
            $version->refresh();

            $this->audit->record(
                AuditAction::ProposalDraftDuplicated,
                $agent,
                AuditSubject::proposal($proposal),
                [],
                [
                    ...ProposalsAuditValues::draft($proposal, $version),
                    'duplicated_from_id' => $original->id,
                    'duplicated_from_number' => $original->number,
                    ...ProposalsAuditValues::clubsOf($evaluation),
                    'list_total' => $version->list_total,
                    ...ProposalsAuditValues::campaign($version),
                ],
            );

            return new ProposalDuplicated(
                $proposal,
                $this->repricing->compare($sourceRows, $source->list_total, $evaluation),
                array_values(array_map(
                    fn (ClubEvaluation $club): string => $club->club->name,
                    array_filter($evaluation->clubs, fn (ClubEvaluation $club): bool => $club->hasErrors()),
                )),
                $evaluation->clubs !== [],
            );
        });
    }

    /**
     * The season of the original while it is not closed (D-317), otherwise the default one (D-375).
     */
    private function season(Proposal $original): ?Season
    {
        $season = $original->season()->first();

        return $season !== null && $season->status !== SeasonStatus::Closed ? $season : $this->period->defaultSeason();
    }

    /**
     * Dates of the copy: those of the original (same season) recalculated on today, otherwise the
     * defaults of the season (D-375).
     *
     * @return array{string, string}
     */
    private function dates(Season $season, ?ProposalVersion $source): array
    {
        $defaultStart = ProposalPeriod::defaultStart($season);
        $defaultEnd = ProposalPeriod::defaultEnd($season);
        $sourceStart = $source?->starts_on?->toDateString();
        $sourceEnd = $source?->ends_on?->toDateString();

        $start = $sourceStart !== null && $sourceStart > $defaultStart && $sourceStart <= $defaultEnd ? $sourceStart : $defaultStart;
        $end = $sourceEnd !== null && $sourceEnd >= $start && $sourceEnd <= $defaultEnd ? $sourceEnd : $defaultEnd;

        return [$start, $end];
    }
}
