<?php

namespace App\Domain\Sports\Data;

/**
 * Data of a stadium from the Stadiums page (M02 F-07).
 */
final readonly class StadiumData
{
    public function __construct(
        public string $name,
        public string $city,
        public ?int $capacity,
        public bool $isActive,
    ) {}
}
