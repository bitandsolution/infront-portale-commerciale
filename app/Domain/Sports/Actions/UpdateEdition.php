<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\AuditAction;
use App\Models\CompetitionSeason;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Change of the standard number of home matches of an edition (D-034, FL-01 step 3): admin only.
 * Refused in a closed season (D-317). Approved proposals are never recomputed (D-041): the number
 * is read only by drafts and new proposals.
 */
final class UpdateEdition
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(CompetitionSeason $edition, int $standardHomeMatches, User $actor): bool
    {
        return DB::transaction(function () use ($edition, $standardHomeMatches, $actor): bool {
            $edition = CompetitionSeason::query()->with(['competition', 'season'])->lockForUpdate()->findOrFail($edition->id);

            if ($edition->season->isReadOnly()) {
                throw SportsActionNotAllowed::seasonClosed($edition->season->code);
            }

            if ($edition->standard_home_matches === $standardHomeMatches) {
                return false;
            }

            $old = ['standard_home_matches' => $edition->standard_home_matches];
            $edition->update(['standard_home_matches' => $standardHomeMatches]);

            $this->audit->record(
                AuditAction::EditionUpdated,
                $actor,
                AuditSubject::edition($edition),
                $old,
                ['standard_home_matches' => $standardHomeMatches],
            );

            return true;
        });
    }
}
