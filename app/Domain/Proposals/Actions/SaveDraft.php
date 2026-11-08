<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Data\ClubEvaluation;
use App\Domain\Proposals\Data\DraftClubData;
use App\Domain\Proposals\Data\DraftData;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Data\DraftSaved;
use App\Domain\Proposals\Data\ProposalsAuditValues;
use App\Domain\Proposals\Exceptions\DraftConflict;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\DraftEvaluator;
use App\Domain\Proposals\Services\ProposalPeriod;
use App\Domain\Settings\Services\Settings;
use App\Enums\AuditAction;
use App\Enums\MatchesSource;
use App\Enums\SeasonStatus;
use App\Models\Client;
use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\PriceList;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Models\ProposalVersionClubSeat;
use App\Models\Season;
use App\Models\Territory;
use App\Models\User;
use App\Models\VisibilityType;
use App\Support\LedDuration;
use App\Support\Text;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Automatic save of a draft (D-165, D-372, D-373): client, season and period (steps 1 and 2, F4-02)
 * and the clubs with their configuration (steps 3 and 4, F4-03). The page saves after 2 seconds
 * without changes and at every change of step, never at every touch.
 *
 * - Optimistic lock (D-216): the page sends the lock_version it read; a different value means the
 *   draft changed in the meantime (another window or device): DraftConflict, nothing is written.
 * - A new client must be an active, not merged client of the agent (D-366, D-368); the current one
 *   stays even if it was deactivated later. A new season must not be closed (D-317).
 * - The period is checked against the season (M06 §8, ProposalPeriod): errors by field.
 * - Clubs: minutes in steps of the settings (D-063) and at most the seats of the settings per club
 *   (D-147, M06-CA5) are refused by field. Then DraftEvaluator evaluates every club; a choice the
 *   agent has just made (a new club, tier, format, more minutes or seats) must be sellable and within
 *   the residual (RB-10, M06-CA5), otherwise it is refused with the message of its field; a choice
 *   already saved that is no longer valid (residual, list or enrolments changed) stays in the draft
 *   with its error (M06 §9, D-373). Territory and visibility type are the only active ones (D-085).
 * - In the same request the server calculates and writes the matches, list prices and club price of
 *   every club and the list total of the version (CLAUDE.md §4; prices never come from the page).
 * - Nothing changed: nothing is written, no event. Otherwise lock_version + 1 and one audit event
 *   proposal.draft_updated with the changed fields only (D-372).
 */
final class SaveDraft
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly DraftEvaluator $evaluator,
        private readonly Settings $settings,
    ) {}

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
            $rows = self::rows($version);
            $before = [
                ...ProposalsAuditValues::draft($locked, $version),
                ...ProposalsAuditValues::clubRows($rows),
                'list_total' => $version->list_total,
            ];

            $client = $this->client($locked, $data->clientId);
            $season = $this->season($locked, $data->seasonId);
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
            $version->fill(['starts_on' => $startsOn, 'ends_on' => $endsOn, 'list_total' => $evaluation->listTotal]);

            $after = [
                ...ProposalsAuditValues::draft($locked, $version),
                ...self::clubValues($evaluation),
            ];

            foreach (array_keys($before) as $field) {
                if (str_starts_with($field, 'club_') && ! array_key_exists($field, $after)) {
                    $after[$field] = null;
                }
            }

            $after['list_total'] = $evaluation->listTotal;

            [$old, $new] = AuditLog::changes($before, $after);

            if ($new === []) {
                return new DraftSaved(false, $locked->lock_version, $locked->updated_at);
            }

            $version->save();
            $this->writeClubs($version, $rows, $evaluation, $season);
            $locked->lock_version = $locked->lock_version + 1;
            $locked->save();

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
     * that is no longer valid stays with its error (M06 §9).
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

    /**
     * The clubs after the save, for the audit (one line per club).
     *
     * @return array<string, string>
     */
    private static function clubValues(DraftEvaluation $evaluation): array
    {
        $tiers = PartnershipTier::query()->pluck('code', 'id');
        $formats = LedFormat::query()->pluck('code', 'id');
        $variants = HospitalityVariant::query()->pluck('code', 'id');
        $values = [];

        foreach ($evaluation->clubs as $club) {
            $seats = [];

            foreach ($club->data->seats as $variantId => $count) {
                $code = $variants->get($variantId);

                if ($count > 0 && is_string($code)) {
                    $seats[$code] = $count;
                }
            }

            $tier = $club->data->tierId !== null ? $tiers->get($club->data->tierId) : null;
            $format = $club->data->ledFormatId !== null ? $formats->get($club->data->ledFormatId) : null;

            $values[ProposalsAuditValues::clubKey($club->club->code)] = ProposalsAuditValues::club(
                is_string($tier) ? $tier : null,
                is_string($format) ? $format : null,
                $club->data->ledSeconds,
                $seats,
            );
        }

        return $values;
    }

    /**
     * Writes the club rows and their seats with what the server calculated; removes the clubs no
     * longer in the draft (their seats go with them, C3).
     *
     * @param  Collection<int, ProposalVersionClub>  $rows
     */
    private function writeClubs(ProposalVersion $version, Collection $rows, DraftEvaluation $evaluation, ?Season $season): void
    {
        $kept = array_map(fn (ClubEvaluation $club): int => $club->club->id, $evaluation->clubs);

        foreach ($rows as $row) {
            if (! in_array($row->club_id, $kept, true)) {
                $row->delete();
            }
        }

        if ($evaluation->clubs === []) {
            return;
        }

        $priceListId = $season !== null ? PriceList::query()->where('season_id', $season->id)->value('id') : null;
        [$territoryId, $visibilityTypeId] = self::hiddenValues();

        foreach ($evaluation->clubs as $index => $club) {
            $row = $rows->firstWhere('club_id', $club->club->id) ?? new ProposalVersionClub([
                'proposal_version_id' => $version->id,
                'club_id' => $club->club->id,
                'territory_id' => $territoryId,
                'visibility_type_id' => $visibilityTypeId,
            ]);
            $quote = $club->quote;
            $data = $club->data;

            $row->fill([
                'sort_order' => $index,
                'partnership_tier_id' => $data->tierId,
                'led_format_id' => $data->ledFormatId,
                'led_seconds_per_match' => $data->ledFormatId !== null ? $data->ledSeconds : null,
                'competition_season_id' => $quote?->matches->edition->id,
                'home_matches' => $quote?->matches->matches,
                'matches_source' => $quote === null ? null : ($quote->matches->fromCalendar ? MatchesSource::Calendar : MatchesSource::Standard),
                'price_list_id' => $quote !== null ? $priceListId : null,
                'led_unit_price' => $quote?->ledUnitPrice,
                'led_price' => $quote?->ledPrice,
                'partnership_price' => $quote?->partnershipPrice,
                'hospitality_price' => $quote?->hospitalityPrice,
                'club_total' => $quote?->total,
            ])->save();

            $this->writeSeats($row, $club);
        }
    }

    private function writeSeats(ProposalVersionClub $row, ClubEvaluation $club): void
    {
        $saved = $row->seats()->get()->keyBy('hospitality_variant_id');
        $wanted = array_filter($club->data->seats, fn (int $seats): bool => $seats > 0);

        foreach ($saved as $variantId => $seat) {
            if (! array_key_exists((int) $variantId, $wanted)) {
                $seat->delete();
            }
        }

        foreach ($wanted as $variantId => $seats) {
            $line = null;

            foreach ($club->quote !== null ? $club->quote->hospitality : [] as $candidate) {
                if ($candidate->variant->id === $variantId) {
                    $line = $candidate;
                }
            }

            $seat = $saved->get($variantId) ?? new ProposalVersionClubSeat([
                'proposal_version_club_id' => $row->id,
                'hospitality_variant_id' => $variantId,
            ]);

            $seat->fill(['seats' => $seats, 'seat_price' => $line?->seatPrice, 'price' => $line?->price])->save();
        }
    }

    /**
     * D-085: territory and visibility type are hidden while a single value is active.
     *
     * @return array{int, int}
     */
    private static function hiddenValues(): array
    {
        $territory = Territory::query()->where('is_active', true)->orderBy('id')->value('id');
        $visibilityType = VisibilityType::query()->where('is_active', true)->orderBy('id')->value('id');

        if (! is_numeric($territory) || ! is_numeric($visibilityType)) {
            throw new RuntimeException('No active territory or visibility type: run SystemDataSeeder.');
        }

        return [(int) $territory, (int) $visibilityType];
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
