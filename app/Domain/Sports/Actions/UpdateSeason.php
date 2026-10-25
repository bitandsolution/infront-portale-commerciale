<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Data\SeasonData;
use App\Domain\Sports\Data\SportsAuditValues;
use App\Domain\Sports\Services\SeasonOverlap;
use App\Enums\AuditAction;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Change of name, dates and status of a season (M02 F-03, D-317): admin only. The code is stable
 * (C3) and is not changed. A closed season can always be reopened: closing only makes its sports
 * registry read-only.
 *
 * Audit season.updated with only the changed fields; no event when nothing changes.
 */
final class UpdateSeason
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly SeasonOverlap $overlap,
    ) {}

    public function handle(Season $season, SeasonData $data, User $actor): bool
    {
        return DB::transaction(function () use ($season, $data, $actor): bool {
            $this->overlap->assertFree($season->discipline_id, $data->startsOn, $data->endsOn, $season->id);

            $season = Season::query()->lockForUpdate()->findOrFail($season->id);
            $before = SportsAuditValues::season($season);

            $season->fill([
                'name' => $data->name,
                'starts_on' => $data->startsOn,
                'ends_on' => $data->endsOn,
                'status' => $data->status,
            ]);

            [$old, $new] = AuditLog::changes($before, SportsAuditValues::season($season));

            if ($new === []) {
                return false;
            }

            $season->save();

            $this->audit->record(AuditAction::SeasonUpdated, $actor, AuditSubject::season($season), $old, $new);

            return true;
        });
    }
}
