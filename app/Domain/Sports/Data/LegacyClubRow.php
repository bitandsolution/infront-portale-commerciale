<?php

namespace App\Domain\Sports\Data;

/**
 * One row of idoc__teams_list in the CSV extracted from the old system (D-318, D-329): HTML entities
 * already decoded, text trimmed. "deleted = 0" marks the Infront clubs of the old system.
 */
final readonly class LegacyClubRow
{
    public function __construct(
        public int $id,
        public string $name,
        public string $alias,
        public string $color,
        public bool $top,
        public bool $deleted,
        public int $category,
    ) {}
}
