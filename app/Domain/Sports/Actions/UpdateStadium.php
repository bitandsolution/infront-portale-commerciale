<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Data\SportsAuditValues;
use App\Domain\Sports\Data\StadiumData;
use App\Enums\AuditAction;
use App\Models\Stadium;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Change or deactivation of a stadium (M02 F-07; C4: never deleted): admin and manager (D-137).
 * Matches keep the stadium saved on them (D-185). Audit with only the changed fields.
 */
final class UpdateStadium
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Stadium $stadium, StadiumData $data, User $actor): bool
    {
        return DB::transaction(function () use ($stadium, $data, $actor): bool {
            $stadium = Stadium::query()->lockForUpdate()->findOrFail($stadium->id);
            $before = SportsAuditValues::stadium($stadium);

            $stadium->fill(CreateStadium::attributes($data));

            [$old, $new] = AuditLog::changes($before, SportsAuditValues::stadium($stadium));

            if ($new === []) {
                return false;
            }

            $stadium->save();

            $this->audit->record(AuditAction::StadiumUpdated, $actor, AuditSubject::stadium($stadium), $old, $new);

            return true;
        });
    }
}
