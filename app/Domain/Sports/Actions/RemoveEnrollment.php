<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\AuditAction;
use App\Models\ClubEnrollment;
use App\Models\FixtureMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Removal of a club from an edition (FL-01 step 2: relegated clubs, a club moved to another Serie C
 * group): admin only. Refused while the club has matches in that edition, even cancelled ones, and
 * in a closed season (D-317). Audit enrollment.removed.
 */
final class RemoveEnrollment
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(ClubEnrollment $enrollment, User $actor): void
    {
        DB::transaction(function () use ($enrollment, $actor): void {
            $enrollment = ClubEnrollment::query()->with(['club', 'competitionSeason.season', 'competitionSeason.competition'])->lockForUpdate()->findOrFail($enrollment->id);
            $edition = $enrollment->competitionSeason;

            if ($edition->season->isReadOnly()) {
                throw SportsActionNotAllowed::seasonClosed($edition->season->code);
            }

            $hasMatches = FixtureMatch::query()
                ->whereHas('matchday', fn ($query) => $query->where('competition_season_id', $edition->id))
                ->where(fn ($query) => $query->where('home_club_id', $enrollment->club_id)->orWhere('away_club_id', $enrollment->club_id))
                ->exists();

            if ($hasMatches) {
                throw SportsActionNotAllowed::enrollmentHasMatches($enrollment->club->name);
            }

            $enrollment->delete();

            $this->audit->record(AuditAction::EnrollmentRemoved, $actor, AuditSubject::edition($edition), ['club' => $enrollment->club->code], []);
        });
    }
}
