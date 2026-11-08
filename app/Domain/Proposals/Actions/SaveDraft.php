<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Data\DraftData;
use App\Domain\Proposals\Data\DraftSaved;
use App\Domain\Proposals\Data\ProposalsAuditValues;
use App\Domain\Proposals\Exceptions\DraftConflict;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\ProposalPeriod;
use App\Enums\AuditAction;
use App\Enums\SeasonStatus;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Automatic save of a draft (D-165, D-372, D-373): steps 1 and 2 of the configurator (client, season,
 * period); F4-03 adds the clubs. The page saves after 2 seconds without changes and at every change
 * of step, never at every touch.
 *
 * - Optimistic lock (D-216): the page sends the lock_version it read; a different value means the
 *   draft changed in the meantime (another window or device): DraftConflict, nothing is written.
 * - A new client must be an active, not merged client of the agent (D-366, D-368); the current one
 *   stays even if it was deactivated later. A new season must not be closed (D-317).
 * - The period is checked against the season (M06 §8, ProposalPeriod): errors by field.
 * - Nothing changed: nothing is written, no event. Otherwise lock_version + 1 and one audit event
 *   proposal.draft_updated with the changed fields only (D-372).
 */
final class SaveDraft
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Proposal $proposal, DraftData $data, int $lockVersion, User $actor): DraftSaved
    {
        return DB::transaction(function () use ($proposal, $data, $lockVersion, $actor): DraftSaved {
            /** @var Proposal $locked */
            $locked = Proposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw ProposalsActionNotAllowed::notDraft($locked->number);
            }

            if ($locked->lock_version !== $lockVersion) {
                throw new DraftConflict;
            }

            /** @var ProposalVersion $version */
            $version = $locked->workingVersion()->lockForUpdate()->firstOrFail();
            $before = ProposalsAuditValues::draft($locked, $version);

            $client = $this->client($locked, $data->clientId);
            $season = $this->season($locked, $data->seasonId);
            $startsOn = $season !== null ? $data->startsOn : null;
            $endsOn = $season !== null ? $data->endsOn : null;

            if ($season !== null && ($errors = ProposalPeriod::errors($season, $startsOn, $endsOn)) !== []) {
                throw ValidationException::withMessages($errors);
            }

            $locked->fill(['client_id' => $client->id, 'season_id' => $season?->id]);
            $locked->setRelation('client', $client)->setRelation('season', $season);
            $version->fill(['starts_on' => $startsOn, 'ends_on' => $endsOn]);

            [$old, $new] = AuditLog::changes($before, ProposalsAuditValues::draft($locked, $version));

            if ($new === []) {
                return new DraftSaved(false, $locked->lock_version, $locked->updated_at);
            }

            $version->save();
            $locked->lock_version = $locked->lock_version + 1;
            $locked->save();

            $this->audit->record(AuditAction::ProposalDraftUpdated, $actor, AuditSubject::proposal($locked), $old, $new);

            return new DraftSaved(true, $locked->lock_version, $locked->updated_at);
        });
    }

    private function client(Proposal $proposal, int $clientId): Client
    {
        $client = Client::query()->find($clientId);

        if ($client !== null && $client->id === $proposal->client_id) {
            return $client;
        }

        if ($client === null || ! CreateDraft::selectable($client, $proposal->agent)) {
            throw ProposalsActionNotAllowed::clientNotSelectable();
        }

        return $client;
    }

    private function season(Proposal $proposal, ?int $seasonId): ?Season
    {
        if ($seasonId === null) {
            return null;
        }

        $season = Season::query()->find($seasonId);

        if ($season !== null && $season->id === $proposal->season_id) {
            return $season;
        }

        if ($season === null || $season->status === SeasonStatus::Closed) {
            throw ProposalsActionNotAllowed::seasonNotSelectable();
        }

        return $season;
    }
}
