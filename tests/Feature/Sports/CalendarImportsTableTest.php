<?php

use App\Enums\CalendarColumn;
use App\Models\CalendarImport;
use App\Models\FixtureMatch;
use App\Models\StoredFile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
| Calendar imports (F-09; progettazione-tecnica §5.3.2; C9; F1-04): constraints written in SQL, as a
| SQL client would, and the recognition of the columns of the layout (D-017, D-325).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
});

it('C9: the database refuses unknown statuses and a confirmation without its date', function () {
    $import = CalendarImport::factory()->create();

    expect(fn () => DB::table('calendar_imports')->where('id', $import->id)->update(['status' => 'pending']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('calendar_imports')->where('id', $import->id)->update(['status' => 'confirmed']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('calendar_imports')->where('id', $import->id)->update(['confirmed_at' => now()]))->toThrow(QueryException::class);
});

it('C3: an import used by a match, and its file, cannot be deleted', function () {
    $import = CalendarImport::factory()->create();
    FixtureMatch::factory()->create(['last_import_id' => $import->id]);

    expect(fn () => DB::table('calendar_imports')->where('id', $import->id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('stored_files')->where('id', $import->file_id)->delete())->toThrow(QueryException::class);
});

it('D-017, D-325: the columns are recognized from the header, also in the common variants', function () {
    expect(CalendarColumn::detect(['Stagione', 'Competizione', 'Giornata', 'Data', 'Ora', 'Squadra casa', 'Squadra ospite', 'Stadio']))
        ->toBe(['season' => 0, 'competition' => 1, 'matchday' => 2, 'date' => 3, 'time' => 4, 'home' => 5, 'away' => 6, 'stadium' => 7])
        ->and(CalendarColumn::detect(['  GG ', 'data', 'Orario', 'Squadra di casa', 'OSPITE', 'Note']))
        ->toBe(['matchday' => 0, 'date' => 1, 'time' => 2, 'home' => 3, 'away' => 4])
        ->and(CalendarColumn::missing(['matchday' => 0, 'home' => 3]))->toBe([CalendarColumn::Date, CalendarColumn::Away]);
});
