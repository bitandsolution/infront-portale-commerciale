<?php

use App\Domain\Documents\Exceptions\DocumentToolUnavailable;
use App\Domain\Documents\Services\DocumentFonts;
use Illuminate\Support\Facades\File;

/*
| Fonts of the documents (D-249, D-311): Interstate from DOCUMENT_FONTS_PATH, or DejaVu as substitute
| only in local and testing. Fake font files carry only the TrueType signature: the real Interstate
| files never enter the repository.
*/

/**
 * A folder with the three Interstate file names, each with the TrueType signature only.
 */
function documentFontsFolder(string $name = 'Font Interstate'): string
{
    $folder = storage_path('framework/testing/'.$name);
    File::ensureDirectoryExists($folder);

    foreach (config('documents.font_files') as $file) {
        file_put_contents($folder.'/'.$file, "\x00\x01\x00\x00".str_repeat("\x00", 60));
    }

    return $folder;
}

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing/Font Interstate'));
});

it('D-311: uses the DejaVu substitute in testing when DOCUMENT_FONTS_PATH is empty', function () {
    config(['documents.fonts_path' => null]);

    $fonts = app(DocumentFonts::class);

    expect($fonts->usesSubstitute())->toBeTrue()
        ->and($fonts->files())->toBe([
            400 => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            700 => '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            900 => '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ]);
})->skip(! is_file('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'), 'fonts-dejavu-core is not installed');

it('D-249: reads Interstate Regular, Bold and Black from DOCUMENT_FONTS_PATH, with explicit weights', function () {
    $folder = documentFontsFolder();
    config(['documents.fonts_path' => $folder.'/']);

    $fonts = app(DocumentFonts::class);
    $css = $fonts->css();

    expect($fonts->usesSubstitute())->toBeFalse()
        ->and($fonts->files())->toBe([
            400 => $folder.'/interstate-regular.ttf',
            700 => $folder.'/interstate-bold.ttf',
            900 => $folder.'/interstate-black.ttf',
        ])
        ->and(substr_count($css, '@font-face'))->toBe(3)
        ->and($css)->toContain("font-family: 'DocumentFont'")
        ->and($css)->toContain('Font%20Interstate/interstate-bold.ttf')
        ->and($css)->toContain("format('truetype'); font-weight: 900;")
        ->and($css)->toStartWith("@font-face { font-family: 'DocumentFont'; src: url('file:///");
});

it('D-249: refuses a missing Interstate file, naming it', function () {
    $folder = documentFontsFolder();
    unlink($folder.'/interstate-black.ttf');
    config(['documents.fonts_path' => $folder]);

    expect(fn () => app(DocumentFonts::class)->files())
        ->toThrow(DocumentToolUnavailable::class, $folder.'/interstate-black.ttf');
});

it('D-249: refuses a file that is not a font (for example Interstate-black-euro.ttf)', function () {
    $folder = documentFontsFolder();
    file_put_contents($folder.'/interstate-bold.ttf', 'not a font');
    config(['documents.fonts_path' => $folder]);

    try {
        app(DocumentFonts::class)->files();
        $this->fail('The invalid font was accepted.');
    } catch (DocumentToolUnavailable $exception) {
        expect($exception->reason)->toBe('font_invalid')
            ->and($exception->getMessage())->toContain('interstate-bold.ttf');
    }
});

it('D-311: refuses the substitute font in production', function () {
    config(['documents.fonts_path' => null]);
    app()->detectEnvironment(fn (): string => 'production');

    try {
        app(DocumentFonts::class)->files();
        $this->fail('The substitute font was accepted in production.');
    } catch (DocumentToolUnavailable $exception) {
        expect($exception->reason)->toBe('fonts_not_configured')
            ->and($exception->getMessage())->toContain('DOCUMENT_FONTS_PATH');
    }
});

it('encodes spaces, quotes and accents of the font path in the file:// URL', function () {
    expect(DocumentFonts::fileUrl("/srv/font d'Infront/città/a b.ttf"))
        ->toBe('file:///srv/font%20d%27Infront/citt%C3%A0/a%20b.ttf');
});
