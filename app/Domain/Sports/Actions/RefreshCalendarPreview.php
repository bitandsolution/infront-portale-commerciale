<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Sports\Data\CalendarPreview;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Domain\Sports\Services\CalendarAnalyzer;
use App\Domain\Sports\Services\CalendarFileReader;
use App\Enums\CalendarColumn;
use App\Models\CalendarImport;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the preview of an open import from its file (FL-02; D-325): after a new column mapping,
 * a name associated with a club, or on request ("Ricalcola l'anteprima"), e.g. after the admin has
 * enrolled the missing clubs. Without a mapping of its own the import keeps the stored one, or the
 * columns recognized from the header row.
 *
 * Refused for a confirmed or discarded import and in a closed season (D-317). No audit event: the
 * preview is not data of the registry (D-321, one event at the confirmation).
 */
final class RefreshCalendarPreview
{
    public function __construct(
        private readonly CalendarFileReader $reader,
        private readonly CalendarAnalyzer $analyzer,
    ) {}

    /**
     * @param  array<string, int>|null  $mapping
     */
    public function handle(CalendarImport $import, ?array $mapping = null): CalendarImport
    {
        return DB::transaction(function () use ($import, $mapping): CalendarImport {
            $import = CalendarImport::query()
                ->with(['competitionSeason.season', 'competitionSeason.competition', 'file'])
                ->lockForUpdate()
                ->findOrFail($import->id);

            self::ensureOpen($import);

            $preview = $this->build($import, $mapping);
            $import->update(['column_mapping' => $preview->mapping, 'preview' => $preview->toArray()]);

            return $import;
        });
    }

    /**
     * Preview of the import as the file and the registry are now, without saving it.
     *
     * @param  array<string, int>|null  $mapping
     */
    public function build(CalendarImport $import, ?array $mapping = null): CalendarPreview
    {
        $sheet = $this->reader->read($import->file);
        $mapping ??= $import->column_mapping !== null ? $import->mapping() : CalendarColumn::detect($sheet->headers);

        return $this->analyzer->analyze($import->competitionSeason, $sheet, $mapping);
    }

    /**
     * FL-02: only an import in preview changes, and never in a closed season (D-317).
     */
    public static function ensureOpen(CalendarImport $import): void
    {
        if (! $import->isOpen()) {
            throw SportsActionNotAllowed::importClosed();
        }

        $season = $import->competitionSeason->season;

        if ($season->isReadOnly()) {
            throw SportsActionNotAllowed::seasonClosed($season->code);
        }
    }
}
