<?php

use App\Domain\Files\Rules\AcceptableFile;
use App\Enums\FilePurpose;
use App\Support\Files\SampleFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/*
| Validation rule of the upload fields (D-218): the same checks as the storage service, so the
| forms of the next slices show the Italian message under the field.
*/

/**
 * Errors of the field "file", or an empty list when the upload passes.
 *
 * @return list<string>
 */
function filesFieldErrors(mixed $value, FilePurpose $purpose): array
{
    $validator = Validator::make(['file' => $value], ['file' => ['required', new AcceptableFile($purpose)]]);

    return array_values($validator->errors()->get('file'));
}

it('M05-CA4: shows a clear message under the field for a PDF logo', function () {
    $upload = UploadedFile::fake()->createWithContent('logo.png', SampleFiles::pdf());

    expect(filesFieldErrors($upload, FilePurpose::ClientLogo))
        ->toBe(['Formato non ammesso: carica un file PNG o JPG.']);
});

it('M08-CA8: shows a clear message under the field for a slide of 1600×900', function () {
    $upload = UploadedFile::fake()->createWithContent('slide.png', SampleFiles::png(1600, 900));

    expect(filesFieldErrors($upload, FilePurpose::SlideImage))
        ->toBe(["L'immagine misura 1600×900 pixel: deve essere esattamente 1920×1080."]);
});

it('D-218: lets a valid upload pass and refuses a value that is not a file', function () {
    $upload = UploadedFile::fake()->createWithContent('logo.jpg', SampleFiles::jpeg(600, 300));

    expect(filesFieldErrors($upload, FilePurpose::ClientLogo))->toBe([])
        ->and(filesFieldErrors('logo.png', FilePurpose::ClientLogo))
        ->toBe(['Il file è danneggiato o non si riesce a leggere.']);
});
