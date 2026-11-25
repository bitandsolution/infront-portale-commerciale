<?php

namespace App\Domain\Content\Data;

/**
 * A club of the simulator (M08 F-04; D-494, D-496): the package (code of the catalogue, null = none),
 * the LED format (code, null = no LED) and whether there are hospitality seats. The competition comes
 * from the enrollment of the club in the season.
 */
final readonly class SimulatedClub
{
    public function __construct(
        public int $clubId,
        public ?string $tierCode,
        public ?string $formatCode,
        public bool $hospitality,
    ) {}
}
