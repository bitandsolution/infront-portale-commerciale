<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Data\CompetitionData;
use App\Domain\Sports\Data\SportsAuditValues;
use App\Enums\AuditAction;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Change of a competition (M02 F-02): admin only. The code is stable (C3) and is not changed; a
 * competition is deactivated, never deleted (C4). Audit with only the changed fields.
 */
final class UpdateCompetition
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Competition $competition, CompetitionData $data, User $actor): bool
    {
        return DB::transaction(function () use ($competition, $data, $actor): bool {
            $competition = Competition::query()->lockForUpdate()->findOrFail($competition->id);
            $before = SportsAuditValues::competition($competition);

            $competition->fill(CreateCompetition::attributes($data));

            [$old, $new] = AuditLog::changes($before, SportsAuditValues::competition($competition));

            if ($new === []) {
                return false;
            }

            $competition->save();

            $this->audit->record(AuditAction::CompetitionUpdated, $actor, AuditSubject::competition($competition), $old, $new);

            return true;
        });
    }
}
