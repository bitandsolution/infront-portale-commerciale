<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Sports\Services\CurrentSeason;
use App\Models\Season;

/**
 * Choice of the season in the top bar (D-317): every role, kept in the session of the user. A view
 * preference, not a change of data: no audit event.
 */
final class SelectSeason
{
    public function __construct(private readonly CurrentSeason $currentSeason) {}

    public function handle(Season $season): void
    {
        $this->currentSeason->select($season);
    }
}
