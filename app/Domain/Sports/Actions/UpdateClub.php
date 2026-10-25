<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Data\ClubData;
use App\Domain\Sports\Data\SportsAuditValues;
use App\Enums\AuditAction;
use App\Models\Club;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Change or deactivation of a club (M02 F-05, RB-08; C4: never deleted): admin only. The code is
 * stable (C3). Approved proposals keep what they froze (D-041). Audit with only the changed fields.
 */
final class UpdateClub
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Club $club, ClubData $data, User $actor): bool
    {
        return DB::transaction(function () use ($club, $data, $actor): bool {
            $club = Club::query()->lockForUpdate()->findOrFail($club->id);
            $before = SportsAuditValues::club($club);

            $club->fill(CreateClub::attributes($data));

            [$old, $new] = AuditLog::changes($before, SportsAuditValues::club($club));

            if ($new === []) {
                return false;
            }

            $club->save();

            $this->audit->record(AuditAction::ClubUpdated, $actor, AuditSubject::club($club), $old, $new);

            return true;
        });
    }
}
