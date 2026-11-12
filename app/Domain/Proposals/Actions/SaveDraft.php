<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Data\DraftClubData;
use App\Domain\Proposals\Data\DraftData;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Data\DraftSaved;
use App\Domain\Proposals\Data\ProposalsAuditValues;
use App\Domain\Proposals\Exceptions\DraftConflict;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\DraftEvaluator;
use App\Domain\Proposals\Services\DraftRowsWriter;
use App\Domain\Proposals\Services\ProposalPeriod;
use App\Domain\Settings\Services\Settings;
use App\Enums\AuditAction;
use App\Enums\SeasonStatus;
use App\Models\Client;
use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Models\ProposalVersionClubSeat;
use App\Models\Season;
use App\Models\User;
use App\Support\LedDuration;
use App\Support\Text;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Automatic save of a draft (D-165, D-372, D-373): client, season and period (steps 1 and 2, F4-02)
 * and the clubs with their configuration (steps 3 and 4, F4-03). The page saves after 2 seconds
 * without changes and at every change of step, never at every touch.
 *
 * - Optimistic lock (D-216): the page sends the lock_version it read; a different value means the
 *   draft changed in the meantime (another window or device): DraftConflict, nothing is written.
 * - A new client must be an active, not merged client of the agent (D-366, D-368); the current one
 *   stays even if it was deactivated later. A new season must not be closed (D-317).
 * - The period is checked against the season (M06 §8, ProposalPeriod): errors by field. A season
 *   already chosen is never removed (M06 §8, D-381).
 * - Clubs: minutes in steps of the settings (D-063) and at most the seats of the settings per club
 *   (D-147, M06-CA5) are refused by field. Then DraftEvaluator evaluates every club; a choice the
 *   agent has just made (a new club, tier, format, more minutes or seats) must be sellable and within
 *   the residual (RB-10, M06-CA5), otherwise it is refused with the message of its field; a choice
 *   already saved that is no longer valid (residual, list or enrolments changed) stays in the draft
 *   with its error (M06 §9, D-373, D-382). Territory and visibility type are the only active ones
 *   (D-085). A club removed from the draft is not confirmed: the removal stays in the audit (D-383).
 * - In the same request the server calculates and writes the matches, list prices and club price of
 *   every club, the list total of the version and, from F4-04, the KPI of the campaign (MatchCounter on
 *   all the clubs together, D-093) (CLAUDE.md §4; prices never come from the page).
 * - Nothing changed: nothing is written, no event. Otherwise lock_version + 1 and one audit event
 *   proposal.draft_updated with the changed fields only (D-372).
 * - Variants (D-396, DM-01): the variants of a group share client and season. While the group has
 *   other variants they cannot change (ProposalsActionNotAllowed); the draft alone in its group can
 *   change them, and the group follows.
 */
final class SaveDraft
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly DraftEvaluator $evaluator,
        private readonly Settings $settings,
        private readonly DraftRowsWriter $rowsWriter,
    ) {}

    public function handle(Proposal $proposal, DraftData $data, int $lockVersion, User $actor): DraftSaved
    {
        return DB::transaction(function () use ($proposal, $data, $lockVersion, $actor): DraftSaved {
            /** @var Proposal $locked */
            $locked = Proposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isEditable()) {
                throw ProposalsActionNotAllowed::notDraft($locked->number);
            }

            if ($locked->lock_version !== $lockVersion) {
                throw new DraftConflict;
            }

            /** @var ProposalVersion $version */
            $version = $locked->workingVersion()->lockForUpdate()->firstOrFail();
            $rows = self::rows($version);
            $before = [
                ...ProposalsAuditValues::draft($locked, $version),
                ...ProposalsAuditValues::clubRows($rows),
                'list_total' => $version->list_total,
                ...ProposalsAuditValues::campaign($version),
            ];

            $client = $this->client($locked, $data->clientId);
            $season = $this->season($locked, $data->seasonId);
            $group = $this->group($locked, $client, $season);
            $startsOn = $season !== null ? $data->startsOn : null;
            $endsOn = $season !== null ? $data->endsOn : null;

            if ($season !== null && ($errors = ProposalPeriod::errors($season, $startsOn, $endsOn)) !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($data->clubs !== null) {
                $this->validateClubs($data->clubs);
            }

            $clubs = $data->clubs ?? self::fromRows($rows);
            $evaluation = $this->evaluator->evaluate($season, $startsOn, $endsOn, $clubs);

            if ($data->clubs !== null) {
                self::refuseNewChoices($rows, $evaluation);
            }

            $locked->fill(['client_id' => $client->id, 'season_id' => $season?->id]);
            $locked->setRelation('client', $client)->setRelation('season', $season);
            $version->fill([
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                ...DraftRowsWriter::versionTotals($evaluation),
            ]);

            $after = [
                ...ProposalsAuditValues::draft($locked, $version),
                ...ProposalsAuditValues::clubsOf($evaluation),
            ];

            foreach (array_keys($before) as $field) {
                if (str_starts_with($field, 'club_') && ! array_key_exists($field, $after)) {
                    $after[$field] = null;
                }
            }

            $after['list_total'] = $evaluation->listTotal;
            $after = [...$after, ...ProposalsAuditValues::campaign($version)];

            [$old, $new] = AuditLog::changes($before, $after);

            if ($new === []) {
                return new DraftSaved(false, $locked->lock_version, $locked->updated_at);
            }

            $version->save();
            $this->rowsWriter->write($version, $rows, $evaluation, $season);
            $locked->lock_version = $locked->lock_version + 1;
            $locked->save();
            $group?->update(['client_id' => $client->id, 'season_id' => $season?->id]);

            $this->audit->record(AuditAction::ProposalDraftUpdated, $actor, AuditSubject::proposal($locked), $old, $new);

            return new DraftSaved(true, $locked->lock_version, $locked->updated_at);
        });
    }

    /**
     * Club rows of the working version in their order, with what the audit and the page need.
     *
     * @return Collection<int, ProposalVersionClub>
     */
    public static function rows(ProposalVersion $version): Collection
    {
        return $version->clubs()
            ->with(['club', 'partnershipTier', 'ledFormat', 'seats.variant'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * The saved clubs as the page would send them: used when the page did not send the clubs, so
     * that their prices follow the new header.
     *
     * @param  Collection<int, ProposalVersionClub>  $rows
     * @return list<DraftClubData>
     */
    public static function fromRows(Collection $rows): array
    {
        $clubs = [];

        foreach ($rows as $row) {
            $seats = [];

            foreach ($row->seats as $seat) {
                $seats[$seat->hospitality_variant_id] = $seat->seats;
            }

            $clubs[] = new DraftClubData(
                clubId: $row->club_id,
                tierId: $row->partnership_tier_id,
                ledFormatId: $row->led_format_id,
                ledSeconds: $row->led_seconds_per_match ?? 0,
                seats: $seats,
            );
        }

        return $clubs;
    }

    /**
     * Values that exist in the catalogue, minutes in steps of the settings and at most the seats of
     * the settings per club: the configurator never sends anything else (M06-CA5).
     *
     * @param  list<DraftClubData>  $clubs
     */
    private function validateClubs(array $clubs): void
    {
        $unknown = Club::query()->whereKey(array_map(fn (DraftClubData $club): int => $club->clubId, $clubs))->count() !== count($clubs)
            || self::missing(PartnershipTier::class, array_filter(array_map(fn (DraftClubData $club): ?int => $club->tierId, $clubs)))
            || self::missing(LedFormat::class, array_filter(array_map(fn (DraftClubData $club): ?int => $club->ledFormatId, $clubs)))
            || self::missing(HospitalityVariant::class, array_merge(...array_map(fn (DraftClubData $club): array => array_keys($club->seats), $clubs)));

        if ($unknown) {
            throw ValidationException::withMessages(['clubs' => Text::get('proposals.club_errors.unknown')]);
        }

        $step = $this->settings->ledStepSeconds();
        $max = $this->settings->hospitalityMaxSeats();
        $errors = [];

        foreach ($clubs as $club) {
            if ($club->ledFormatId !== null && ($club->ledSeconds <= 0 || $club->ledSeconds % $step !== 0)) {
                $errors[self::field($club->clubId, 'led_seconds')] = Text::get('proposals.club_errors.led_step', ['step' => LedDuration::format($step)]);
            }

            if ($club->totalSeats() > $max) {
                $errors[self::field($club->clubId, 'seats')] = Text::get('proposals.club_errors.seats_max', ['max' => (string) $max]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  class-string<PartnershipTier|LedFormat|HospitalityVariant>  $model
     * @param  array<int, int>  $ids
     */
    private static function missing(string $model, array $ids): bool
    {
        $ids = array_values(array_unique($ids));

        return $ids !== [] && $model::query()->whereKey($ids)->count() !== count($ids);
    }

    /**
     * A choice just made must be sellable and within the residual (RB-10, D-064); one already saved
     * that is no longer valid stays with its error (M06 §9, D-382).
     *
     * @param  Collection<int, ProposalVersionClub>  $rows
     */
    private static function refuseNewChoices(Collection $rows, DraftEvaluation $evaluation): void
    {
        $errors = [];

        foreach ($evaluation->clubs as $club) {
            $row = $rows->firstWhere('club_id', $club->club->id);
            $data = $club->data;
            $refuse = function (string $field) use (&$errors, $club): void {
                if (isset($club->errors[$field])) {
                    $errors[self::field($club->club->id, $field)] = $club->errors[$field];
                }
            };

            if ($row === null && $club->sellability?->isSelectable() !== true) {
                $refuse('club');

                continue;
            }

            if ($row?->partnership_tier_id !== $data->tierId) {
                $refuse('tier');
            }

            if ($row?->led_format_id !== $data->ledFormatId) {
                $refuse('led_format');
                $refuse('led_seconds');
            } elseif ($data->ledSeconds > ($row !== null ? ($row->led_seconds_per_match ?? 0) : 0)) {
                $refuse('led_seconds');
            }

            foreach ($data->seats as $variantId => $seats) {
                $saved = $row?->seats->firstWhere('hospitality_variant_id', $variantId);

                if ($seats > ($saved instanceof ProposalVersionClubSeat ? $saved->seats : 0)) {
                    $refuse('seats.'.$variantId);
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private static function field(int $clubId, string $field): string
    {
        return 'clubs.'.$clubId.'.'.$field;
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

    /**
     * The group to align when the draft changes client or season (D-396): refused while the group
     * has other variants; locked after the proposal, as DeleteDraft does.
     */
    private function group(Proposal $proposal, Client $client, ?Season $season): ?ProposalGroup
    {
        if ($proposal->proposal_group_id === null || ($client->id === $proposal->client_id && $season?->id === $proposal->season_id)) {
            return null;
        }

        if (Proposal::query()->where('proposal_group_id', $proposal->proposal_group_id)->whereKeyNot($proposal->id)->exists()) {
            throw ProposalsActionNotAllowed::variantsShareHeader();
        }

        return ProposalGroup::query()->whereKey($proposal->proposal_group_id)->lockForUpdate()->first();
    }

    private function season(Proposal $proposal, ?int $seasonId): ?Season
    {
        // M06 §8: the season is required; a draft is without one only while none can be chosen
        // (D-381). Once chosen it is changed, never removed.
        if ($seasonId === null && $proposal->season_id !== null) {
            throw ValidationException::withMessages(['season_id' => Text::get('proposals.validation.season_required')]);
        }

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
