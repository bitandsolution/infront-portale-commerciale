<?php

use App\Domain\Files\Exceptions\InvalidFile;
use App\Domain\Files\Exceptions\StoredFileImmutable;
use App\Domain\Files\Services\FileStorage;
use App\Enums\FilePurpose;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Files\SampleFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| Storage service (C10, D-218): type from the content, limits of each purpose, re-encoding of the
| images, SHA-256 and pixels of the stored file, private disk, files never overwritten.
| Every sample is generated in the test (App\Support\Files\SampleFiles, GD): no real file.
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
});

/**
 * Reason of the refusal of a content, or null when the file is accepted.
 */
function filesRefusal(string $contents, FilePurpose $purpose): ?string
{
    try {
        storeTestFile($purpose, null, $contents);

        return null;
    } catch (InvalidFile $invalid) {
        return $invalid->reason;
    }
}

function filesStoredContents(StoredFile $file): string
{
    return (string) Storage::disk(StoredFile::DISK)->get($file->path);
}

it('M05-CA4: refuses a PDF as client logo, with an Italian message', function () {
    expect(fn () => storeTestFile(FilePurpose::ClientLogo, null, SampleFiles::pdf()))
        ->toThrow(InvalidFile::class, 'Formato non ammesso: carica un file PNG o JPG.');

    expect(StoredFile::query()->count())->toBe(0)
        ->and(Storage::disk(StoredFile::DISK)->allFiles())->toBe([]);
});

it('M05-CA4: refuses a client logo of 3 MB, with an Italian message', function () {
    $logo = SampleFiles::padded(SampleFiles::png(600, 300), 3 * 1024 * 1024);

    expect(fn () => storeTestFile(FilePurpose::ClientLogo, null, $logo))
        ->toThrow(InvalidFile::class, 'Il file pesa 3 MB: il massimo è 2 MB.');
});

it('M05 §6: accepts a client logo of exactly 2 MB', function () {
    $logo = SampleFiles::padded(SampleFiles::png(600, 300), 2 * 1024 * 1024);

    expect(filesRefusal($logo, FilePurpose::ClientLogo))->toBeNull();
});

it('D-218: recognises the type from the content, not from the name', function (string $contents) {
    expect(filesRefusal($contents, FilePurpose::ClientLogo))->toBe('type_not_allowed');
})->with([
    'PHP renamed logo.png' => [SampleFiles::php()],
    'HTML renamed logo.png' => [SampleFiles::html()],
    'SVG' => [SampleFiles::svg()],
    'Excel' => [SampleFiles::xlsx()],
]);

it('D-218: a PNG with a .pdf name is stored as PNG, with the .png extension', function () {
    $file = storeTestFile(FilePurpose::ClientLogo, null, SampleFiles::png(600, 300), 'logo.pdf');

    expect($file->mime_type)->toBe('image/png')
        ->and($file->path)->toEndWith('.png')
        ->and($file->downloadName())->toBe('logo.png');
});

it('M08-CA8: refuses a PNG of 1600×900 as slide, with an Italian message', function () {
    expect(fn () => storeTestFile(FilePurpose::SlideImage, null, SampleFiles::png(1600, 900)))
        ->toThrow(InvalidFile::class, "L'immagine misura 1600×900 pixel: deve essere esattamente 1920×1080.");
});

it('M08 §7: accepts a PNG slide of 1920×1080 and refuses a JPG one', function () {
    $file = storeTestFile(FilePurpose::SlideImage, null, SampleFiles::png(1920, 1080), 'slide.png');

    expect($file->width)->toBe(1920)
        ->and($file->height)->toBe(1080)
        ->and(filesRefusal(SampleFiles::jpeg(1920, 1080), FilePurpose::SlideImage))->toBe('type_not_allowed');
});

it('M08 §7: refuses a slide over 10 MB', function () {
    $slide = SampleFiles::padded(SampleFiles::png(1920, 1080), 10 * 1024 * 1024 + 1);

    expect(filesRefusal($slide, FilePurpose::SlideImage))->toBe('too_large');
});

it('C10: stores the image re-encoded, with the same pixels and without the metadata', function () {
    $original = SampleFiles::png(600, 300, 'Autore: Mario Rossi');

    $file = storeTestFile(FilePurpose::ClientLogo, null, $original);
    $stored = filesStoredContents($file);
    $size = getimagesizefromstring($stored);

    expect($stored)->not->toBe($original)
        ->and($stored)->not->toContain('tEXt')
        ->and($stored)->not->toContain('Mario Rossi')
        ->and($size)->not->toBeFalse()
        ->and([$size[0] ?? 0, $size[1] ?? 0])->toBe([600, 300])
        ->and([$file->width, $file->height])->toBe([600, 300]);
});

it('D-218: drops the EXIF data of a JPG logo', function () {
    $original = SampleFiles::jpeg(600, 300, exif: true);
    expect($original)->toContain('Exif');

    $file = storeTestFile(FilePurpose::ClientLogo, null, $original, 'logo.jpg');
    $stored = filesStoredContents($file);

    expect($file->mime_type)->toBe('image/jpeg')
        ->and($file->path)->toEndWith('.jpg')
        ->and($stored)->not->toContain('Exif')
        ->and($stored)->not->toContain('Test camera');
});

it('D-218: drops PHP code hidden after a valid PNG', function () {
    $file = storeTestFile(FilePurpose::ClientLogo, null, SampleFiles::png(300, 100).SampleFiles::php());

    expect(filesStoredContents($file))->not->toContain('<?php');
});

it('C10: saves MIME type, size and SHA-256 of the stored file', function () {
    $file = storeTestFile(FilePurpose::ClientLogo);
    $stored = filesStoredContents($file);

    expect($file->size_bytes)->toBe(strlen($stored))
        ->and($file->sha256)->toBe(hash('sha256', $stored))
        ->and($file->disk)->toBe(StoredFile::DISK)
        ->and($file->purpose)->toBe(FilePurpose::ClientLogo);
});

it('D-218: refuses a JPG in CMYK, a damaged image and a logo over 5000 pixels', function (string $contents, string $reason) {
    expect(filesRefusal($contents, FilePurpose::ClientLogo))->toBe($reason);
})->with([
    'CMYK' => [SampleFiles::cmykJpeg(), 'cmyk'],
    'damaged PNG' => [substr(SampleFiles::png(600, 300), 0, 40), 'unreadable'],
    '5001 pixels wide' => [SampleFiles::png(5001, 10), 'too_many_pixels'],
    'empty' => ['', 'empty'],
]);

it('D-218: accepts an .xlsx calendar as it is, and refuses macros and old formats', function () {
    $workbook = SampleFiles::xlsx();
    $file = storeTestFile(FilePurpose::CalendarImport, null, $workbook, 'calendario.xlsx');

    expect(filesStoredContents($file))->toBe($workbook)
        ->and($file->mime_type)->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($file->width)->toBeNull()
        ->and(filesRefusal(SampleFiles::xlsx(macros: true), FilePurpose::CalendarImport))->toBe('macros')
        ->and(filesRefusal(SampleFiles::xls(), FilePurpose::CalendarImport))->toBe('type_not_allowed')
        ->and(filesRefusal(SampleFiles::png(600, 300), FilePurpose::CalendarImport))->toBe('type_not_allowed');
});

it('M09: stores a PDF generated by the portal as it is', function () {
    $pdf = SampleFiles::pdf();
    $file = storeTestFile(FilePurpose::GeneratedPdf, null, $pdf, null);

    expect(filesStoredContents($file))->toBe($pdf)
        ->and($file->downloadName())->toBe('generated-pdf-'.$file->id.'.pdf');
});

it('C10: builds the path from purpose, month and a new ULID, never from the name chosen by the user', function () {
    $this->travelTo(now()->setDate(2026, 10, 25));

    $file = storeTestFile(FilePurpose::ClientLogo, null, null, '../../../public/shell.php');

    expect($file->path)->toMatch('#^client_logo/2026/10/[0-9a-z]{26}\.png$#')
        ->and($file->original_name)->toBe('shell.php')
        ->and($file->downloadName())->toBe('shell.png')
        ->and(Storage::disk(StoredFile::DISK)->exists($file->path))->toBeTrue();
});

it('C10: cleans the original name from folders and control characters', function (?string $name, ?string $clean) {
    expect(FileStorage::cleanName($name))->toBe($clean);
})->with([
    'Windows path' => ['C:\\Users\\mario\\Logo Cliente.png', 'Logo Cliente.png'],
    'control characters' => ["logo\x00\x1F.png", 'logo.png'],
    'only dots' => ['..', null],
    'missing' => [null, null],
    'accents kept' => ['Società Sportiva.png', 'Società Sportiva.png'],
]);

it('M05-CA5: a new logo is a new file, the previous one stays, also with the same content', function () {
    $content = SampleFiles::png(600, 300);

    $first = storeTestFile(FilePurpose::ClientLogo, null, $content);
    $second = storeTestFile(FilePurpose::ClientLogo, null, $content);

    expect($second->id)->not->toBe($first->id)
        ->and($second->path)->not->toBe($first->path)
        ->and($second->sha256)->toBe($first->sha256)
        ->and(Storage::disk(StoredFile::DISK)->exists($first->path))->toBeTrue()
        ->and(Storage::disk(StoredFile::DISK)->exists($second->path))->toBeTrue();
});

it('C10: never overwrites a file already on the disk', function () {
    $ulid = Str::ulid();
    Str::createUlidsUsing(fn () => $ulid);

    try {
        $first = storeTestFile(FilePurpose::ClientLogo);
        $before = filesStoredContents($first);

        expect(fn () => storeTestFile(FilePurpose::ClientLogo, null, SampleFiles::png(800, 400)))
            ->toThrow(StoredFileImmutable::class);
        expect(filesStoredContents($first))->toBe($before);
    } finally {
        Str::createUlidsNormally();
    }
});

it('C10: records the author, null for the system', function () {
    $admin = User::factory()->admin()->create();

    expect(storeTestFile(FilePurpose::ClientLogo, $admin)->creator?->is($admin))->toBeTrue()
        ->and(storeTestFile(FilePurpose::ClientLogo)->created_by)->toBeNull();
});

it('C10: deletes the file just written when the transaction of the action is rolled back', function () {
    $path = null;

    try {
        DB::transaction(function () use (&$path): void {
            $path = app(FileStorage::class)->storeContents(SampleFiles::png(600, 300), FilePurpose::ClientLogo, null)->path;

            throw new RuntimeException('Action failed after the upload');
        });
    } catch (RuntimeException) {
    }

    expect($path)->not->toBeNull()
        ->and(Storage::disk(StoredFile::DISK)->exists((string) $path))->toBeFalse()
        ->and(StoredFile::query()->count())->toBe(0);
});

it('C10: stores only inside the transaction of an action', function () {
    // Feature tests run inside the transaction of RefreshDatabase: a second connection to the same
    // database has no transaction open.
    config(['database.connections.files_outside' => config('database.connections.'.DB::getDefaultConnection())]);
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('files_outside');

    try {
        expect(fn () => app(FileStorage::class)->storeContents(SampleFiles::png(600, 300), FilePurpose::ClientLogo, null))
            ->toThrow(LogicException::class, 'inside the transaction');
    } finally {
        DB::setDefaultConnection($default);
    }

    expect(Storage::disk(StoredFile::DISK)->allFiles())->toBe([]);
});

it('D-218: stores an uploaded file with the name chosen by the user', function () {
    $upload = UploadedFile::fake()->createWithContent('Logo Rossi.png', SampleFiles::png(600, 300));

    $file = DB::transaction(fn (): StoredFile => app(FileStorage::class)->storeUpload($upload, FilePurpose::ClientLogo, null));

    expect($file->original_name)->toBe('Logo Rossi.png')
        ->and($file->mime_type)->toBe('image/png');
});
