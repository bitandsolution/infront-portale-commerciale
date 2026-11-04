<?php

namespace App\Domain\Clients\Data;

/**
 * Data of a client from the client form (M05 §6; D-081, D-361): the VAT number is already cleaned
 * (11 digits or null, VatNumber::clean); the free text only with a sector that asks for it (RB-04).
 * The owner is not here: CreateClient receives it, the reassignment arrives with F3-03.
 */
final readonly class ClientData
{
    public function __construct(
        public string $companyName,
        public ?string $vatNumber,
        public string $email,
        public int $sectorId,
        public ?string $sectorFreeText,
        public ?string $contactName,
    ) {}
}
