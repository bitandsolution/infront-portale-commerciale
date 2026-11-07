<?php

namespace App\Domain\Clients\Data;

use App\Models\Client;
use App\Models\CommoditySector;

/**
 * Fields of the clients area written in the audit events (D-127): AuditLog::changes() keeps only the
 * changed ones.
 */
final class ClientsAuditValues
{
    /**
     * @return array<string, mixed>
     */
    public static function sector(CommoditySector $sector): array
    {
        return [
            'name' => $sector->name,
            'sort_order' => $sector->sort_order,
            'is_active' => $sector->is_active,
        ];
    }

    /**
     * The client fields of the events client.created and client.updated: the sector by its stable
     * code, the owner and the logo by id (the logo file is never overwritten, C10).
     *
     * @return array<string, mixed>
     */
    public static function client(Client $client, CommoditySector $sector): array
    {
        return [
            'company_name' => $client->company_name,
            'vat_number' => $client->vat_number,
            'email' => $client->email,
            'commodity_sector_code' => $sector->code,
            'sector_free_text' => $client->sector_free_text,
            'contact_name' => $client->contact_name,
            'owner_id' => $client->owner_id,
            'logo_file_id' => $client->logo_file_id,
        ];
    }

    /**
     * The owner of a client in the event client.reassigned (D-082): id and name, since the name of
     * a deactivated agent is what the admin looks for in the audit.
     *
     * @return array{owner_id: int, owner_name: string}
     */
    public static function owner(Client $client): array
    {
        return [
            'owner_id' => $client->owner_id,
            'owner_name' => $client->owner->fullName(),
        ];
    }

    /**
     * Status of a client in the events client.deactivated, client.reactivated and client.merged
     * (D-359): the client it was merged into by id and name.
     *
     * @return array{is_active: bool, merged_into_id: int|null, merged_into_name: string|null}
     */
    public static function status(Client $client): array
    {
        return [
            'is_active' => $client->is_active,
            'merged_into_id' => $client->merged_into_id,
            'merged_into_name' => $client->mergedInto?->company_name,
        ];
    }
}
