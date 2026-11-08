<?php

namespace App\Support\Proposals;

use App\Domain\Inventory\Data\ClubSellability;
use App\Domain\Inventory\Data\SellableOption;
use App\Domain\Proposals\Data\ClubEvaluation;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Services\ProposalPeriod;
use App\Enums\CompetitionType;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\Season;
use App\Models\User;
use App\Support\Amounts;
use App\Support\Clients\ClientsPresenter;
use App\Support\LedDuration;
use App\Support\Text;
use Illuminate\Support\Carbon;

/**
 * Props of the configurator pages (F4-02, F4-03): "Nuova proposta", a draft (steps 1–4) and
 * "Le mie proposte". Prices, totals, matches, residuals and errors are always the server's
 * (CLAUDE.md §4): the page only shows them; dates of the period are sent both as "Y-m-d" (fields)
 * and in Italian (texts); system timestamps in Europe/Rome (C7).
 */
final class ProposalsPresenter
{
    public const TIMEZONE = 'Europe/Rome';

    /**
     * Clients the agent can choose (M05 F-02, FL-01; D-366, D-368): his own, active and not merged.
     * The search with suggestions runs in the page, on this list.
     *
     * @return array<int, array{value: string, label: string, vatNumber: string|null, sector: string}>
     */
    public static function clientOptions(User $agent): array
    {
        return Client::query()
            ->visibleTo($agent)
            ->where('owner_id', $agent->id)
            ->where('is_active', true)
            ->whereNull('merged_into_id')
            ->with('sector')
            ->orderBy('company_name')
            ->get()
            ->map(fn (Client $client): array => [
                'value' => (string) $client->id,
                'label' => $client->company_name,
                'vatNumber' => $client->vat_number,
                'sector' => ClientsPresenter::sectorLabel($client),
            ])
            ->values()
            ->all();
    }

    /**
     * Seasons of step 2 (not closed) with their limits and the defaults of the period (D-375).
     *
     * @return array<int, array{value: string, label: string, startsOn: string, endsOn: string, range: string, defaultStartsOn: string, defaultEndsOn: string}>
     */
    public static function seasonOptions(ProposalPeriod $period): array
    {
        return $period->seasons()
            ->map(fn (Season $season): array => [
                'value' => (string) $season->id,
                'label' => $season->code,
                'startsOn' => $season->starts_on->toDateString(),
                'endsOn' => $season->ends_on->toDateString(),
                'range' => Text::get('ui.proposals.period.season_range', [
                    'from' => $season->starts_on->format('d/m/Y'),
                    'to' => $season->ends_on->format('d/m/Y'),
                ]),
                'defaultStartsOn' => ProposalPeriod::defaultStart($season),
                'defaultEndsOn' => ProposalPeriod::defaultEnd($season),
            ])
            ->values()
            ->all();
    }

    /**
     * Clubs of step 3 (M06 RB-09, D-085): the selectable clubs of the season with their league and
     * the options shown at step 4 (available, or sold out and not selectable; "not managed" and "not
     * available" are hidden, FL-02, ux-architettura §4.5), with residuals and list prices.
     *
     * @param  list<ClubSellability>  $clubs
     * @return array<int, array<string, mixed>>
     */
    public static function clubOptions(?Season $season, array $clubs): array
    {
        $leagues = $season !== null ? self::leagues($season) : [];
        $options = [];

        foreach ($clubs as $sellability) {
            $club = $sellability->club;
            $options[] = [
                'id' => $club->id,
                'name' => $club->name,
                'initials' => self::initials($club),
                'competition' => $leagues[$club->id] ?? null,
                'tiers' => array_map(fn (SellableOption $option): array => [
                    ...self::option($option),
                    'isFree' => $option->isFreePackage,
                    'price' => $option->listPrice !== null ? Amounts::italian($option->listPrice) : null,
                ], ClubSellability::visible($sellability->partnership)),
                'formats' => array_map(fn (SellableOption $option): array => [
                    ...self::option($option),
                    'remainingLabel' => $option->remaining !== null ? LedDuration::format($option->remaining) : null,
                ], ClubSellability::visible($sellability->led)),
                'variants' => array_map(fn (SellableOption $option): array => [
                    ...self::option($option),
                    'price' => $option->listPrice !== null ? Amounts::italian($option->listPrice) : null,
                ], ClubSellability::visible($sellability->hospitality)),
            ];
        }

        return $options;
    }

    /**
     * @return array{id: int, name: string, status: string, statusLabel: string, remaining: int|null}
     */
    private static function option(SellableOption $option): array
    {
        return [
            'id' => $option->valueId,
            'name' => $option->name,
            'status' => $option->status->value,
            'statusLabel' => $option->status->label(),
            'remaining' => $option->remaining,
        ];
    }

    /**
     * League of each club in the season (D-185: one per club), by club id.
     *
     * @return array<int, string>
     */
    private static function leagues(Season $season): array
    {
        return ClubEnrollment::query()
            ->whereHas('competitionSeason', fn ($query) => $query
                ->where('season_id', $season->id)
                ->whereHas('competition', fn ($competition) => $competition->where('type', CompetitionType::League->value)))
            ->with('competitionSeason.competition')
            ->get()
            ->mapWithKeys(fn (ClubEnrollment $enrollment): array => [$enrollment->club_id => $enrollment->competitionSeason->competition->name])
            ->all();
    }

    private static function initials(Club $club): string
    {
        return mb_strtoupper(mb_substr($club->short_name !== '' ? $club->short_name : $club->name, 0, 3));
    }

    /**
     * One club of the draft (steps 3 and 4): what the agent chose, the matches, the club price and the
     * errors found by the server (DraftEvaluator), by field.
     *
     * @return array<string, mixed>
     */
    private static function draftClub(ClubEvaluation $evaluation): array
    {
        $quote = $evaluation->quote;
        $data = $evaluation->data;
        $matches = $quote?->matches;
        $seats = [];

        foreach ($data->seats as $variantId => $count) {
            $seats[] = ['variantId' => $variantId, 'seats' => $count];
        }

        return [
            'clubId' => $evaluation->club->id,
            'name' => $evaluation->club->name,
            'initials' => self::initials($evaluation->club),
            'competition' => $matches?->edition->competition->name,
            'selectable' => $evaluation->sellability?->isSelectable() ?? false,
            'tierId' => $data->tierId,
            'ledFormatId' => $data->ledFormatId,
            'ledSeconds' => $data->ledSeconds,
            'seats' => $seats,
            'matches' => $matches?->matches,
            'matchesLabel' => $matches === null ? null : Text::get(
                $matches->fromCalendar ? 'ui.proposals.configuration.matches_calendar' : 'ui.proposals.configuration.matches_standard',
                ['count' => (string) $matches->matches],
            ),
            // Spots per match = minutes × 4 (M06 §6): one every 15 seconds.
            'spots' => $matches !== null && $data->ledFormatId !== null ? intdiv($data->ledSeconds, 15) * $matches->matches : null,
            'price' => $quote !== null ? Amounts::italian($quote->total) : null,
            'errors' => $evaluation->errors,
        ];
    }

    /**
     * A draft open in the configurator; the clubs and the total come from the evaluation of the
     * server, made on the current list and residuals.
     *
     * @return array<string, mixed>
     */
    public static function draft(Proposal $proposal, User $agent, ?DraftEvaluation $evaluation = null): array
    {
        /** @var ProposalVersion $version */
        $version = $proposal->workingVersion;
        $client = $proposal->client;

        return [
            'id' => $proposal->id,
            'number' => $proposal->number,
            'title' => Text::get('proposals.label', ['number' => (string) $proposal->number]),
            'lockVersion' => $proposal->lock_version,
            'savedAt' => self::time($proposal->updated_at),
            'clientId' => $client !== null ? (string) $client->id : '',
            'client' => $client !== null ? [
                'name' => $client->company_name,
                'vatNumber' => $client->vat_number,
                'sector' => ClientsPresenter::sectorLabel($client),
                // A client deactivated or merged after the draft was made stays on it, with a note.
                'selectable' => $client->is_active && ! $client->isMerged() && $client->isOwnedBy($agent),
            ] : null,
            'seasonId' => $proposal->season_id !== null ? (string) $proposal->season_id : '',
            'startsOn' => $version->starts_on?->toDateString() ?? '',
            'endsOn' => $version->ends_on?->toDateString() ?? '',
            'listTotal' => Amounts::italian($evaluation !== null ? $evaluation->listTotal : $version->list_total),
            'clubs' => $evaluation !== null
                ? array_map(fn (ClubEvaluation $club): array => self::draftClub($club), $evaluation->clubs)
                : [],
            'hasErrors' => $evaluation?->hasErrors() ?? false,
            'saveUrl' => route('proposals.update', $proposal),
            'deleteUrl' => route('proposals.destroy', $proposal),
            'newClientUrl' => route('proposals.clients.update', $proposal),
        ];
    }

    /**
     * One draft in "Le mie proposte" (D-374).
     *
     * @return array<string, mixed>
     */
    public static function draftRow(Proposal $proposal): array
    {
        $version = $proposal->workingVersion;

        return [
            'id' => $proposal->id,
            'number' => $proposal->number,
            'client' => $proposal->client?->company_name,
            'season' => $proposal->season?->code,
            'period' => $version?->starts_on !== null && $version->ends_on !== null
                ? Text::get('ui.proposals.mine.period', [
                    'from' => $version->starts_on->format('d/m/Y'),
                    'to' => $version->ends_on->format('d/m/Y'),
                ])
                : null,
            'listTotal' => Amounts::italian($version !== null ? $version->list_total : '0'),
            'updatedAt' => $proposal->updated_at->copy()->setTimezone(self::TIMEZONE)->format('d/m/Y H:i'),
            'editUrl' => route('proposals.edit', $proposal),
            'deleteUrl' => route('proposals.destroy', $proposal),
        ];
    }

    public static function time(Carbon $moment): string
    {
        return $moment->copy()->setTimezone(self::TIMEZONE)->format('H:i');
    }
}
