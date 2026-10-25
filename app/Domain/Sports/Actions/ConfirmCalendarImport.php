<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Data\CalendarPreview;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\CalendarImportStatus;
use App\Enums\MatchdayStage;
use App\Enums\MatchSource;
use App\Enums\MatchStatus;
use App\Models\CalendarImport;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Confirmation of a calendar import (FL-02 steps 4 and 5; F-09, F-10; D-322: in the request).
 *
 * In one transaction, with the edition locked: the file is analyzed again against the registry as it
 * is now (the stored preview may be old), then the new matches are inserted (regular stage, origin
 * "import", D-325) and the changed ones updated on their natural key (date, time, stadium; a
 * cancelled match stays cancelled). Rows in error are discarded; matches missing from the file are
 * left as they are (D-319). The edition gets calendar_loaded_at; the import keeps the final preview
 * and the counters of the summary.
 *
 * One audit event for the whole import, with the counters (D-321, M10 §4.1).
 */
final class ConfirmCalendarImport
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly RefreshCalendarPreview $preview,
    ) {}

    public function handle(CalendarImport $import, User $actor): CalendarImport
    {
        return DB::transaction(function () use ($import, $actor): CalendarImport {
            $import = CalendarImport::query()->with('file')->lockForUpdate()->findOrFail($import->id);
            $edition = CompetitionSeason::query()->with(['season', 'competition'])->lockForUpdate()->findOrFail($import->competition_season_id);
            $import->setRelation('competitionSeason', $edition);

            RefreshCalendarPreview::ensureOpen($import);

            $preview = $this->preview->build($import);

            if ($preview->missingColumns !== [] || $preview->validRows() === 0) {
                throw SportsActionNotAllowed::importNothingToConfirm();
            }

            $this->write($preview, $edition, $import);
            $counts = $preview->counts();

            $edition->update(['calendar_loaded_at' => now()]);
            $import->update([
                'status' => CalendarImportStatus::Confirmed,
                'column_mapping' => $preview->mapping,
                'preview' => $preview->toArray(),
                'inserted_count' => $counts['new'],
                'updated_count' => $counts['update'],
                'unchanged_count' => $counts['unchanged'],
                'discarded_count' => $counts['error'],
                'confirmed_at' => now(),
                'confirmed_by' => $actor->id,
            ]);

            $this->audit->record(AuditAction::CalendarImportConfirmed, $actor, AuditSubject::edition($edition), [], [
                'file' => $import->file->original_name,
                'inserted' => $counts['new'],
                'updated' => $counts['update'],
                'unchanged' => $counts['unchanged'],
                'discarded' => $counts['error'],
            ]);

            return $import;
        });
    }

    private function write(CalendarPreview $preview, CompetitionSeason $edition, CalendarImport $import): void
    {
        /** @var array<int, Matchday> $matchdays */
        $matchdays = [];

        foreach ($preview->rows as $row) {
            if ($row['outcome'] === CalendarPreview::NEW && $row['matchday'] !== null) {
                $matchday = $matchdays[$row['matchday']] ??= Matchday::query()->firstOrCreate([
                    'competition_season_id' => $edition->id,
                    'stage' => MatchdayStage::Regular,
                    'number' => $row['matchday'],
                ]);

                FixtureMatch::query()->create([
                    'matchday_id' => $matchday->id,
                    'home_club_id' => $row['home_club_id'],
                    'away_club_id' => $row['away_club_id'],
                    'stadium_id' => $row['stadium_id'],
                    'match_date' => $row['date'],
                    'kickoff_time' => $row['time'],
                    'status' => MatchStatus::Scheduled,
                    'source' => MatchSource::Import,
                    'last_import_id' => $import->id,
                ]);
            }

            if ($row['outcome'] === CalendarPreview::UPDATE && $row['match_id'] !== null) {
                FixtureMatch::query()->lockForUpdate()->findOrFail($row['match_id'])->update([
                    'match_date' => $row['date'],
                    'kickoff_time' => $row['time'],
                    'stadium_id' => $row['stadium_id'],
                    'last_import_id' => $import->id,
                ]);
            }
        }
    }
}
