<?php

namespace App\Domain\Sports\Services;

use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Models\Discipline;
use App\Models\Season;
use Illuminate\Support\Carbon;

/**
 * M02 §8: seasons of the same discipline never overlap. A rule on more rows, so it lives in the code
 * (C9). Called inside the transaction of the Action, after locking the discipline row: two admins
 * saving overlapping seasons at the same moment are serialized.
 */
final class SeasonOverlap
{
    public function assertFree(int $disciplineId, Carbon $startsOn, Carbon $endsOn, ?int $ignoreSeasonId = null): void
    {
        Discipline::query()->whereKey($disciplineId)->lockForUpdate()->first();

        $query = Season::query()->where('discipline_id', $disciplineId);

        if ($ignoreSeasonId !== null) {
            $query->whereKeyNot($ignoreSeasonId);
        }

        $other = $query
            ->whereDate('starts_on', '<=', $endsOn->toDateString())
            ->whereDate('ends_on', '>=', $startsOn->toDateString())
            ->orderBy('starts_on')
            ->first();

        if ($other !== null) {
            throw SportsActionNotAllowed::seasonOverlaps($other->code);
        }
    }
}
