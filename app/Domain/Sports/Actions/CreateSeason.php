<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Data\SeasonData;
use App\Domain\Sports\Data\SportsAuditValues;
use App\Domain\Sports\Services\SeasonOverlap;
use App\Enums\AuditAction;
use App\Models\Discipline;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * New season (M02 F-03): admin only (D-137). In the 1.0 the season is created from scratch; the
 * copy from the previous season is in release 1.1 (F-04, D-236).
 *
 * The season never overlaps another one of the same discipline (M02 §8). Audit season.created with
 * every field (D-127), in the same transaction.
 */
final class CreateSeason
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly SeasonOverlap $overlap,
    ) {}

    public function handle(SeasonData $data, User $actor): Season
    {
        return DB::transaction(function () use ($data, $actor): Season {
            $discipline = Discipline::default();

            $this->overlap->assertFree($discipline->id, $data->startsOn, $data->endsOn);

            $season = Season::query()->create([
                'discipline_id' => $discipline->id,
                'code' => $data->code,
                'name' => $data->name,
                'starts_on' => $data->startsOn,
                'ends_on' => $data->endsOn,
                'status' => $data->status,
            ]);

            $this->audit->record(
                AuditAction::SeasonCreated,
                $actor,
                AuditSubject::season($season),
                [],
                SportsAuditValues::season($season),
            );

            return $season;
        });
    }
}
