<?php

namespace App\Domain\Sports\Data;

use App\Enums\MatchdayStage;
use Illuminate\Support\Carbon;

/**
 * Data of a match typed in the Matches page (M02 F-08). A null stadium means the home stadium of
 * the home club (D-185); a null kick-off time means not known yet.
 */
final readonly class MatchData
{
    public function __construct(
        public int $competitionSeasonId,
        public MatchdayStage $stage,
        public int $matchdayNumber,
        public Carbon $matchDate,
        public ?string $kickoffTime,
        public int $homeClubId,
        public int $awayClubId,
        public ?int $stadiumId,
    ) {}
}
