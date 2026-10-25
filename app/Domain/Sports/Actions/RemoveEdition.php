<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Data\SportsAuditValues;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\AuditAction;
use App\Models\CompetitionSeason;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Removal of a competition added to a season by mistake: admin only, only while the edition is
 * empty. Enrollments, matchdays and calendar imports point to the edition with ON DELETE RESTRICT
 * (C3): as soon as one exists the database refuses the deletion and the edition stays (C4).
 * Refused in a closed season (D-317). Audit edition.removed with the values it had.
 */
final class RemoveEdition
{
    /** MariaDB: "Cannot delete or update a parent row: a foreign key constraint fails". */
    private const FOREIGN_KEY_VIOLATION = 1451;

    public function __construct(private readonly AuditLog $audit) {}

    public function handle(CompetitionSeason $edition, User $actor): void
    {
        $edition = CompetitionSeason::query()->with(['competition', 'season'])->findOrFail($edition->id);
        $label = $edition->label();

        if ($edition->season->isReadOnly()) {
            throw SportsActionNotAllowed::seasonClosed($edition->season->code);
        }

        try {
            DB::transaction(function () use ($edition, $actor): void {
                $subject = AuditSubject::edition($edition);
                $values = SportsAuditValues::edition($edition);

                $edition->delete();

                $this->audit->record(AuditAction::EditionRemoved, $actor, $subject, $values, []);
            });
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === self::FOREIGN_KEY_VIOLATION) {
                throw SportsActionNotAllowed::editionInUse($label);
            }

            throw $exception;
        }
    }
}
