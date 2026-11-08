<?php

namespace App\Domain\Proposals\Data;

/**
 * A draft saved by the configurator: client (step 1), season and period (step 2, "Y-m-d" calendar
 * days; null only while no season can be chosen) and, from F4-03, the clubs with their configuration
 * (steps 3 and 4). Clubs null = not sent (e.g. the client created from step 1): the clubs of the draft
 * stay as they are, and their prices are calculated again on the new header.
 */
final readonly class DraftData
{
    /**
     * @param  list<DraftClubData>|null  $clubs
     */
    public function __construct(
        public int $clientId,
        public ?int $seasonId,
        public ?string $startsOn,
        public ?string $endsOn,
        public ?array $clubs = null,
    ) {}
}
