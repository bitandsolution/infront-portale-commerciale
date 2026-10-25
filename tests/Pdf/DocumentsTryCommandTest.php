<?php

use App\Enums\FilePurpose;
use App\Models\StoredFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
| Development command documents:try (fetta S1, D-309): runs the pipeline on a sample deck, stores the
| PDF for the admin and reports time, weight and checks. Needs the database (stored_files).
*/

uses(RefreshDatabase::class);

afterEach(function () {
    File::deleteDirectory(storage_path('app/document-try'));
});

it('S1: documents:try generates the deck, stores the PDF and reports weight and times', function () {
    Storage::fake(StoredFile::DISK);

    $exitCode = Artisan::call('documents:try', ['--pages' => 6]);
    $output = Artisan::output();
    $file = StoredFile::query()->sole();

    expect($exitCode)->toBe(0)
        ->and($file->purpose)->toBe(FilePurpose::GeneratedPdf)
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->created_by)->toBeNull()
        ->and($output)->toContain('DejaVu')
        ->and($output)->toContain('M09-CA4')
        ->and($output)->toContain('/file/'.$file->id)
        ->and(glob(storage_path('app/document-try/*/anteprima-recap-1.png')) ?: [])->not->toBeEmpty();
});

it('D-309: documents:try --samples needs DOCUMENT_SAMPLES_PATH with real images', function () {
    config(['documents.samples_path' => null]);

    expect(Artisan::call('documents:try', ['--samples' => true]))->toBe(1)
        ->and(Artisan::output())->toContain('DOCUMENT_SAMPLES_PATH');
});
