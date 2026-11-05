<?php

namespace App\Domain\Clients\Services;

use App\Models\Client;

/**
 * Duplicate check on the whole registry (M05 RB-02, RB-03; D-077, D-080, D-197): a client is already
 * present when it has the same VAT number, or the same normalized company name unless both clients
 * have a VAT number and the two differ ("Rossi Srl" 01234567897 and "Rossi Spa" with another VAT
 * number are two companies; "Rossi Spa" without a VAT number is a duplicate of "Rossi Srl").
 *
 * Every client counts, also the deactivated and merged ones: they are still in the registry and the
 * admin can reactivate or reassign them (F3-03). The caller holds the registry lock (ClientRegistry)
 * so two creations at the same time cannot both pass the check.
 */
final class DuplicateClients
{
    public function find(string $normalizedName, ?string $vatNumber, ?int $ignoreId = null): ?Client
    {
        if ($vatNumber !== null) {
            $sameVat = Client::query()
                ->where('vat_number', $vatNumber)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->first();

            if ($sameVat !== null) {
                return $sameVat;
            }
        }

        return Client::query()
            ->where('company_name_normalized', $normalizedName)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->when($vatNumber !== null, fn ($query) => $query->whereNull('vat_number'))
            ->orderBy('id')
            ->first();
    }
}
