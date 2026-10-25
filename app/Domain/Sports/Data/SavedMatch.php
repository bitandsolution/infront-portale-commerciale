<?php

namespace App\Domain\Sports\Data;

use App\Models\FixtureMatch;

/**
 * Outcome of SaveMatch: the match and the warnings that do not block the save (M02 §8: a club with
 * two matches on the same day in the same competition).
 */
final readonly class SavedMatch
{
    /**
     * @param  list<string>  $warnings  Italian texts
     */
    public function __construct(
        public FixtureMatch $match,
        public bool $changed,
        public array $warnings,
    ) {}
}
