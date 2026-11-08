<?php

namespace App\Support\Clients;

use App\Enums\Role;
use App\Models\Client;
use App\Models\CommoditySector;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Data of the clients area for the Inertia pages (the pages never compute anything themselves).
 */
final class ClientsPresenter
{
    /**
     * @return array{id: int, code: string, name: string, requiresFreeText: bool, isActive: bool, sortOrder: int, url: string}
     */
    public static function sectorRow(CommoditySector $sector): array
    {
        return [
            'id' => $sector->id,
            'code' => $sector->code,
            'name' => $sector->name,
            'requiresFreeText' => $sector->requires_free_text,
            'isActive' => $sector->is_active,
            'sortOrder' => $sector->sort_order,
            'url' => route('clients.sectors.edit', $sector),
        ];
    }

    /**
     * A row of the client lists. The owner (with its id and whether the agent is still active, for the
     * filters of D-360) and the client a merged client went into are shown only to the back office
     * (the agent sees only his own clients and never the merged ones).
     *
     * @return array{id: int, companyName: string, vatNumber: string|null, email: string, sector: string, contactName: string|null, owner: string|null, ownerId: int|null, ownerActive: bool|null, isActive: bool, status: string, mergedInto: string|null, logoUrl: string, url: string}
     */
    public static function clientRow(Client $client, bool $withOwner): array
    {
        return [
            'id' => $client->id,
            'companyName' => $client->company_name,
            'vatNumber' => $client->vat_number,
            'email' => $client->email,
            'sector' => self::sectorLabel($client),
            'contactName' => $client->contact_name,
            'owner' => $withOwner ? $client->owner->fullName() : null,
            'ownerId' => $withOwner ? $client->owner_id : null,
            'ownerActive' => $withOwner ? $client->owner->is_active : null,
            'isActive' => $client->is_active,
            'status' => self::status($client),
            'mergedInto' => $withOwner ? $client->mergedInto?->company_name : null,
            'logoUrl' => route('files.download', $client->logo_file_id),
            'url' => route('clients.edit', $client),
        ];
    }

    /**
     * The fields of the client form. The client a merged client went into is named only to the back
     * office (D-359); the agent sees only that the client was merged.
     *
     * @return array{id: int, companyName: string, vatNumber: string, email: string, sectorId: int, sectorFreeText: string, contactName: string, owner: string, ownerActive: bool, isActive: bool, status: string, mergedInto: array{name: string, url: string}|null, logoUrl: string}
     */
    public static function clientForm(Client $client, bool $backOffice = false): array
    {
        $mergedInto = $client->mergedInto;

        return [
            'id' => $client->id,
            'companyName' => $client->company_name,
            'vatNumber' => $client->vat_number ?? '',
            'email' => $client->email,
            'sectorId' => $client->commodity_sector_id,
            'sectorFreeText' => $client->sector_free_text ?? '',
            'contactName' => $client->contact_name ?? '',
            'owner' => $client->owner->fullName(),
            'ownerActive' => $client->owner->is_active,
            'isActive' => $client->is_active,
            'status' => self::status($client),
            'mergedInto' => $backOffice && $mergedInto !== null
                ? ['name' => $mergedInto->company_name, 'url' => route('clients.edit', $mergedInto)]
                : null,
            'logoUrl' => route('files.download', $client->logo_file_id),
        ];
    }

    /**
     * The admin actions on a client (M05 F-07, F-08, F-09; D-082, D-359), only those allowed now: a
     * merged client has none. Agents for the reassignment: the active ones except the owner. Clients
     * that can stay in a merge: the active, not merged ones except the client itself.
     *
     * @return array{reassignUrl: string|null, deactivateUrl: string|null, reactivateUrl: string|null, mergeUrl: string|null, owners: array<int, array{value: string, label: string}>, mergeTargets: array<int, array{value: string, label: string}>}
     */
    public static function management(Client $client): array
    {
        $merged = $client->isMerged();

        /** @var Collection<int, Client> $targets */
        $targets = $merged ? collect() : Client::query()
            ->with('owner')
            ->where('is_active', true)
            ->whereNull('merged_into_id')
            ->whereKeyNot($client->id)
            ->orderBy('company_name')
            ->orderBy('id')
            ->get();

        return [
            'reassignUrl' => $merged ? null : route('clients.reassign', $client),
            'deactivateUrl' => ! $merged && $client->is_active ? route('clients.deactivate', $client) : null,
            'reactivateUrl' => ! $merged && ! $client->is_active ? route('clients.reactivate', $client) : null,
            'mergeUrl' => $merged ? null : route('clients.merge', $client),
            'owners' => $merged ? [] : array_values(array_filter(
                self::ownerOptions(),
                fn (array $option): bool => $option['value'] !== (string) $client->owner_id,
            )),
            'mergeTargets' => $targets
                ->map(fn (Client $target): array => ['value' => (string) $target->id, 'label' => self::mergeLabel($target)])
                ->values()
                ->all(),
        ];
    }

    /**
     * Sectors that can be chosen: the active ones, plus the current sector of the client even when
     * deactivated (RB-08). The free-text flag lets the form show the free-text field (RB-04).
     *
     * @return array<int, array{value: string, label: string, requiresFreeText: bool}>
     */
    public static function sectorOptions(?Client $client = null): array
    {
        return CommoditySector::query()
            ->where(fn ($query) => $query->where('is_active', true)
                ->when($client !== null, fn ($query) => $query->orWhere('id', $client?->commodity_sector_id)))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CommoditySector $sector): array => [
                'value' => (string) $sector->id,
                'label' => $sector->name,
                'requiresFreeText' => $sector->requires_free_text,
            ])
            ->values()
            ->all();
    }

    /**
     * Active agents the admin can choose as owner of a new client (D-358).
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function ownerOptions(): array
    {
        /** @var Collection<int, User> $agents */
        $agents = User::query()->where('role', Role::Agent)->where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get();

        return $agents
            ->map(fn (User $agent): array => ['value' => (string) $agent->id, 'label' => $agent->fullName().' ('.$agent->email.')'])
            ->values()
            ->all();
    }

    /**
     * Status of a client in the lists and on its page: active, not active, merged (D-359).
     */
    private static function status(Client $client): string
    {
        return match (true) {
            $client->isMerged() => 'merged',
            $client->is_active => 'active',
            default => 'inactive',
        };
    }

    /**
     * A client that can stay in a merge, e.g. "Rossi Srl · P.IVA 01234567897 · Anna Alfa".
     */
    private static function mergeLabel(Client $client): string
    {
        return implode(' · ', array_filter([
            $client->company_name,
            $client->vat_number !== null ? 'P.IVA '.$client->vat_number : null,
            $client->owner->fullName(),
        ]));
    }

    /**
     * Sector of a client, with its free text when the sector asks for one (RB-04); also used by the
     * configurator (F4-02).
     */
    public static function sectorLabel(Client $client): string
    {
        return $client->sector_free_text !== null
            ? $client->sector->name.': '.$client->sector_free_text
            : $client->sector->name;
    }
}
