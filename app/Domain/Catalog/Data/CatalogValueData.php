<?php

namespace App\Domain\Catalog\Data;

/**
 * Data of a value of the catalogue from the page "Catalogo › Valori" (D-331). Description and active
 * are null for the kinds that do not have them (CatalogValueKind).
 */
final readonly class CatalogValueData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public int $sortOrder,
        public ?bool $isActive,
    ) {}
}
