<?php

namespace App\Domain\Sports\Data;

/**
 * Data of a club from the Clubs page (M02 F-05). The code is used only on creation (stable, C3);
 * colors are "#RRGGBB" in capital letters or null.
 */
final readonly class ClubData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $shortName,
        public ?int $homeStadiumId,
        public ?string $primaryColor,
        public ?string $secondaryColor,
        public bool $isInfront,
        public bool $isTopMatchOpponent,
        public bool $isActive,
    ) {}
}
