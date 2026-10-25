<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\MatchStatus;
use App\Models\FixtureMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancellation or restore of a match (M02 §9, D-319): admin and manager. Matches are never deleted;
 * a cancelled match is excluded from the count of the drafts (F1-05), approved proposals keep what
 * they froze (D-041). Refused in a closed season (D-317). Audit match.cancelled / match.restored.
 */
final class ChangeMatchStatus
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(FixtureMatch $match, MatchStatus $status, User $actor): bool
    {
        return DB::transaction(function () use ($match, $status, $actor): bool {
            $match = FixtureMatch::query()->with(['matchday.competitionSeason.season', 'homeClub', 'awayClub'])->lockForUpdate()->findOrFail($match->id);
            $season = $match->matchday->competitionSeason->season;

            if ($season->isReadOnly()) {
                throw SportsActionNotAllowed::seasonClosed($season->code);
            }

            if ($match->status === $status) {
                return false;
            }

            $old = $match->status;
            $match->update(['status' => $status]);

            $this->audit->record(
                $status === MatchStatus::Cancelled ? AuditAction::MatchCancelled : AuditAction::MatchRestored,
                $actor,
                AuditSubject::match($match),
                ['status' => $old->value],
                ['status' => $status->value],
            );

            return true;
        });
    }
}
