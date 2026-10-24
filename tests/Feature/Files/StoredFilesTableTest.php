<?php

use App\Domain\Files\Exceptions\StoredFileImmutable;
use App\Enums\FilePurpose;
use App\Models\StoredFile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| stored_files (C10, D-184; CLAUDE.md §11: trigger tests). Rows are inserted directly in SQL here,
| as a SQL client would: the constraints and the trigger hold for every writer, not only the code.
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function filesRawRow(array $overrides = []): int
{
    return DB::table('stored_files')->insertGetId(array_merge([
        'disk' => 'files',
        'path' => 'client_logo/2026/10/'.strtolower((string) Str::ulid()).'.png',
        'purpose' => 'client_logo',
        'mime_type' => 'image/png',
        'size_bytes' => 1234,
        'sha256' => hash('sha256', 'prova'),
        'width' => 600,
        'height' => 300,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('C10: has the columns of the file model, with the author as a restrictive foreign key', function () {
    expect(Schema::getColumnListing('stored_files'))->toEqualCanonicalizing([
        'id', 'disk', 'path', 'purpose', 'mime_type', 'size_bytes', 'sha256', 'width', 'height',
        'original_name', 'created_by', 'created_at', 'updated_at',
    ]);

    $foreignKeys = Schema::getForeignKeys('stored_files');
    expect($foreignKeys)->toHaveCount(1)
        ->and($foreignKeys[0]['columns'])->toBe(['created_by'])
        ->and($foreignKeys[0]['foreign_table'])->toBe('users')
        ->and(strtolower((string) $foreignKeys[0]['on_delete']))->toBe('restrict');

    $indexes = collect(Schema::getIndexes('stored_files'));
    expect($indexes->firstWhere('columns', ['disk', 'path'])['unique'] ?? false)->toBeTrue()
        ->and($indexes->pluck('columns')->all())->toContain(['sha256'])->toContain(['purpose', 'created_at']);
});

it('C10: refuses two rows with the same path on the same disk', function () {
    filesRawRow(['path' => 'client_logo/2026/10/stesso.png']);

    expect(fn () => filesRawRow(['path' => 'client_logo/2026/10/stesso.png']))
        ->toThrow(QueryException::class);
});

it('C9: refuses an empty size, an invalid SHA-256 and half the dimensions', function () {
    $cases = [
        'size 0' => ['size_bytes' => 0],
        'SHA-256 too short' => ['sha256' => str_repeat('a', 63)],
        'SHA-256 in capitals' => ['sha256' => strtoupper(hash('sha256', 'prova'))],
        'SHA-256 not hexadecimal' => ['sha256' => str_repeat('z', 64)],
        'width without height' => ['height' => null],
        'zero height' => ['height' => 0],
    ];

    $accepted = [];
    foreach ($cases as $case => $row) {
        try {
            filesRawRow($row);
            $accepted[] = $case;
        } catch (QueryException) {
        }
    }

    expect($accepted)->toBe([], 'Rows accepted by the database: '.implode(', ', $accepted));
});

it('C9: accepts a file without dimensions, such as an Excel', function () {
    $id = filesRawRow(['purpose' => 'calendar_import', 'width' => null, 'height' => null]);

    expect(DB::table('stored_files')->where('id', $id)->exists())->toBeTrue();
});

it('C10: refuses an UPDATE written directly in SQL, so a file is never overwritten', function () {
    $id = filesRawRow();

    expect(fn () => DB::update("UPDATE stored_files SET path = 'client_logo/altro.png' WHERE id = ?", [$id]))
        ->toThrow(QueryException::class, 'immutable');
    expect(fn () => DB::update('UPDATE stored_files SET sha256 = ? WHERE id = ?', [hash('sha256', 'altro'), $id]))
        ->toThrow(QueryException::class, 'immutable');
});

it('C10: the model refuses to save a row again', function () {
    Storage::fake(StoredFile::DISK);
    $file = storeTestFile(FilePurpose::ClientLogo);

    $file->original_name = 'altro.png';

    expect(fn () => $file->save())->toThrow(StoredFileImmutable::class);
});
