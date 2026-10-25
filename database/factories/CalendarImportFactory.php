<?php

namespace Database\Factories;

use App\Domain\Files\Services\FileStorage;
use App\Enums\CalendarImportStatus;
use App\Enums\FilePurpose;
use App\Models\CalendarImport;
use App\Models\CompetitionSeason;
use App\Models\User;
use App\Support\Files\SampleFiles;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * Fictitious calendar imports in preview, with an empty workbook as file and an empty preview. The
 * tests of the import upload real files built with OpenSpout.
 *
 * @extends Factory<CalendarImport>
 */
class CalendarImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_season_id' => CompetitionSeason::factory(),
            'file_id' => fn (): int => DB::transaction(fn (): int => app(FileStorage::class)
                ->storeContents(SampleFiles::xlsx(), FilePurpose::CalendarImport, null, 'calendario-prova.xlsx')->id),
            'uploaded_by' => User::factory()->manager(),
            'status' => CalendarImportStatus::Preview,
            'column_mapping' => null,
            'preview' => ['headers' => [], 'mapping' => [], 'missing_columns' => [], 'rows' => [], 'unknown_names' => [], 'missing_matches' => []],
        ];
    }
}
