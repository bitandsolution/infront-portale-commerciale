<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Data\SportsAuditValues;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\AuditAction;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * New edition (competition × season, D-185) with its standard number of home matches (D-034):
 * admin only (D-137, D-321). Refused in a closed season (D-317) and when the edition exists.
 */
final class AddCompetitionToSeason
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Season $season, Competition $competition, int $standardHomeMatches, User $actor): CompetitionSeason
    {
        return DB::transaction(function () use ($season, $competition, $standardHomeMatches, $actor): CompetitionSeason {
            $season = Season::query()->lockForUpdate()->findOrFail($season->id);

            if ($season->isReadOnly()) {
                throw SportsActionNotAllowed::seasonClosed($season->code);
            }

            if ($season->competitionSeasons()->where('competition_id', $competition->id)->exists()) {
                throw SportsActionNotAllowed::editionExists($competition->name.' '.$season->code);
            }

            $edition = CompetitionSeason::query()->create([
                'competition_id' => $competition->id,
                'season_id' => $season->id,
                'standard_home_matches' => $standardHomeMatches,
            ]);
            $edition->setRelation('competition', $competition)->setRelation('season', $season);

            $this->audit->record(
                AuditAction::EditionCreated,
                $actor,
                AuditSubject::edition($edition),
                [],
                SportsAuditValues::edition($edition),
            );

            return $edition;
        });
    }
}
