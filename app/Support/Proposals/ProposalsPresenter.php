<?php

namespace App\Support\Proposals;

use App\Domain\Proposals\Services\ProposalPeriod;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\Season;
use App\Models\User;
use App\Support\Amounts;
use App\Support\Clients\ClientsPresenter;
use App\Support\Text;
use Illuminate\Support\Carbon;

/**
 * Props of the configurator pages (F4-02): "Nuova proposta", a draft (steps 1 and 2) and
 * "Le mie proposte". Prices and totals are always the server's (CLAUDE.md §4); dates of the period are
 * sent both as "Y-m-d" (fields) and in Italian (texts); system timestamps in Europe/Rome (C7).
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
     * A draft open in the configurator.
     *
     * @return array<string, mixed>
     */
    public static function draft(Proposal $proposal, User $agent): array
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
            'listTotal' => Amounts::italian($version->list_total),
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
