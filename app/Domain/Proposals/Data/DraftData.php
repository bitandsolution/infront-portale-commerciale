<?php

namespace App\Domain\Proposals\Data;

/**
 * Header of a draft saved by the configurator (steps 1 and 2, F4-02): client, season and period
 * ("Y-m-d" calendar days). Season and dates are null only while no season can be chosen.
 */
final readonly class DraftData
{
    public function __construct(
        public int $clientId,
        public ?int $seasonId,
        public ?string $startsOn,
        public ?string $endsOn,
    ) {}
}
