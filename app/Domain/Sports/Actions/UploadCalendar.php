<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Files\Services\FileStorage;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\FilePurpose;
use App\Models\CalendarImport;
use App\Models\CompetitionSeason;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Upload of a calendar file for an edition (M02 F-09, FL-02 step 1; D-137: admin and manager).
 * The file is checked and stored by FileStorage (D-218: .xlsx without macros), then read and
 * analyzed: the import starts in preview, with the columns recognized from the header (D-325).
 * A file that cannot be read is refused and nothing stays (the stored file is removed with the
 * rollback). Refused in a closed season (D-317). No audit event until the confirmation (D-321).
 */
final class UploadCalendar
{
    public function __construct(
        private readonly FileStorage $files,
        private readonly RefreshCalendarPreview $preview,
    ) {}

    public function handle(CompetitionSeason $edition, UploadedFile $file, User $actor): CalendarImport
    {
        return DB::transaction(function () use ($edition, $file, $actor): CalendarImport {
            $edition = CompetitionSeason::query()->with(['season', 'competition'])->findOrFail($edition->id);

            if ($edition->season->isReadOnly()) {
                throw SportsActionNotAllowed::seasonClosed($edition->season->code);
            }

            $stored = $this->files->storeUpload($file, FilePurpose::CalendarImport, $actor);

            $import = CalendarImport::query()->create([
                'competition_season_id' => $edition->id,
                'file_id' => $stored->id,
                'uploaded_by' => $actor->id,
            ]);
            $import->setRelation('competitionSeason', $edition)->setRelation('file', $stored);

            $preview = $this->preview->build($import);
            $import->update(['column_mapping' => $preview->mapping, 'preview' => $preview->toArray()]);

            return $import;
        });
    }
}
