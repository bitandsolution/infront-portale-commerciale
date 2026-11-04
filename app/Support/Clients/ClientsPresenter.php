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
     * A row of the client lists. The owner is shown only to the back office (the agent sees only his
     * own clients).
     *
     * @return array{id: int, companyName: string, vatNumber: string|null, email: string, sector: string, contactName: string|null, owner: string|null, isActive: bool, logoUrl: string, url: string}
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
            'isActive' => $client->is_active,
            'logoUrl' => route('files.download', $client->logo_file_id),
            'url' => route('clients.edit', $client),
        ];
    }

    /**
     * The fields of the client form.
     *
     * @return array{id: int, companyName: string, vatNumber: string, email: string, sectorId: int, sectorFreeText: string, contactName: string, owner: string, isActive: bool, logoUrl: string}
     */
    public static function clientForm(Client $client): array
    {
        return [
            'id' => $client->id,
            'companyName' => $client->company_name,
            'vatNumber' => $client->vat_number ?? '',
            'email' => $client->email,
            'sectorId' => $client->commodity_sector_id,
            'sectorFreeText' => $client->sector_free_text ?? '',
            'contactName' => $client->contact_name ?? '',
            'owner' => $client->owner->fullName(),
            'isActive' => $client->is_active,
            'logoUrl' => route('files.download', $client->logo_file_id),
        ];
    }

    /**
     * Sectors that can be chosen: the active ones, plus the current sector of the client even when
     * deactivated (RB-08). The free-text flag lets the form show the free-text field (RB-04).
     *
     * @return list<array{value: string, label: string, requiresFreeText: bool}>
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
     * @return list<array{value: string, label: string}>
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

    private static function sectorLabel(Client $client): string
    {
        return $client->sector_free_text !== null
            ? $client->sector->name.': '.$client->sector_free_text
            : $client->sector->name;
    }
}
