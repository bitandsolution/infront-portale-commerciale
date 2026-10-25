<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\CompetitionType;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Enrollment of clubs in an edition (M02 F-06, FL-01 step 2): admin only (D-137, D-321).
 *
 * - A club already in the edition is refused; a club already in another league of the same season
 *   is refused (one league per club and season, D-185: a rule on more rows, C9).
 * - Refused in a closed season (D-317).
 * - Audit enrollment.created for every club, in the same transaction; all or nothing.
 */
final class EnrollClubs
{
    public function __construct(private readonly AuditLog $audit) {}

    /**
     * @param  list<int>  $clubIds
     * @return list<ClubEnrollment>
     */
    public function handle(CompetitionSeason $edition, array $clubIds, User $actor): array
    {
        /** @var list<ClubEnrollment> $enrolled */
        $enrolled = DB::transaction(function () use ($edition, $clubIds, $actor): array {
            $edition = CompetitionSeason::query()->with(['competition', 'season'])->lockForUpdate()->findOrFail($edition->id);

            if ($edition->season->isReadOnly()) {
                throw SportsActionNotAllowed::seasonClosed($edition->season->code);
            }

            $enrolled = [];

            foreach (Club::query()->whereKey($clubIds)->orderBy('name')->get() as $club) {
                $this->assertCanEnroll($edition, $club);

                $enrollment = ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $club->id]);

                $this->audit->record(
                    AuditAction::EnrollmentCreated,
                    $actor,
                    AuditSubject::edition($edition),
                    [],
                    ['club' => $club->code],
                );

                $enrolled[] = $enrollment;
            }

            return $enrolled;
        });

        return $enrolled;
    }

    private function assertCanEnroll(CompetitionSeason $edition, Club $club): void
    {
        $others = ClubEnrollment::query()
            ->where('club_id', $club->id)
            ->whereHas('competitionSeason', fn ($query) => $query->where('season_id', $edition->season_id))
            ->with('competitionSeason.competition')
            ->get();

        foreach ($others as $other) {
            if ($other->competition_season_id === $edition->id) {
                throw SportsActionNotAllowed::clubAlreadyEnrolled($club->name);
            }

            if ($edition->competition->type === CompetitionType::League
                && $other->competitionSeason->competition->type === CompetitionType::League) {
                throw SportsActionNotAllowed::clubInAnotherLeague($club->name, $other->competitionSeason->competition->name);
            }
        }
    }
}
