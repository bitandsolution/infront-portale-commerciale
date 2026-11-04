<?php

namespace App\Domain\Clients\Data;

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
}
