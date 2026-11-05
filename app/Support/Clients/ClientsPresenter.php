<?php

namespace App\Support\Clients;

use App\Models\CommoditySector;

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
}
