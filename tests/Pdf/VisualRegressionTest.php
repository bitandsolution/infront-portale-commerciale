<?php

use App\Domain\Documents\Data\DocumentPage;
use App\Domain\Documents\Data\StaticSlidePage;
use App\Domain\Documents\Layouts\NetworkRecapLayout;
use App\Domain\Documents\Services\PdfPipeline;
use App\Support\Documents\SampleDeck;
use Illuminate\Support\Facades\File;

/*
| Visual regression of the layouts (layout-dinamici §6.5, D-311): each case is rendered by the same
| pipeline as the PDF and compared with the reference image in tests/Pdf/references, both reduced
| to 960×540 (tolerant of anti-aliasing). The references of the layouts are kept at full size
| (1920×1080), so the thin borders of the grids are visible when Antonio approves them; the static
| slide is kept reduced, since a full-size photo-like PNG would weigh megabytes. Cases: 2 clubs, discount with hospitality, 12 clubs on two slides, long
| names, static slide. DejaVu substitute font, so CI and the VM draw the same pictures.
|
| A missing reference is written by the CI job "pdf" (VISUAL_REFERENCES_WRITE_MISSING=true) and
| committed to the branch of the pull request: Antonio approves it by looking at the image in the
| pull request. Anywhere else a missing reference fails the test. On a difference, the actual image
| and a map of the different pixels are written to storage/framework/testing/visual.
*/

const VISUAL_WIDTH = 960;

const VISUAL_HEIGHT = 540;

// A pixel counts as different when a channel differs by more than this (anti-aliasing tolerance).
const VISUAL_CHANNEL_TOLERANCE = 48;

// The images match when at most this share of the pixels is different.
const VISUAL_MAX_DIFFERENT = 0.005;

/**
 * The page of a case, with its images written into $folder.
 */
function visualCasePage(string $case, string $folder): DocumentPage
{
    if ($case === 'static_slide') {
        file_put_contents($folder.'/statica.png', SampleDeck::staticSlide(1));

        return new StaticSlidePage($folder.'/statica.png');
    }

    file_put_contents($folder.'/sfondo.png', SampleDeck::background('Infront Network - Recap proposta commerciale'));

    [$clubs, $discount, $longNames, $page] = match ($case) {
        'recap_two_clubs' => [2, SampleDeck::DISCOUNT_NONE, false, 0],
        'recap_discount_hospitality' => [3, SampleDeck::DISCOUNT_PERCENT, false, 0],
        'recap_twelve_clubs_page_1' => [12, SampleDeck::DISCOUNT_PERCENT, false, 0],
        'recap_twelve_clubs_page_2' => [12, SampleDeck::DISCOUNT_PERCENT, false, 1],
        'recap_long_names' => [3, SampleDeck::DISCOUNT_AMOUNT, true, 0],
        default => throw new InvalidArgumentException("Unknown visual case {$case}"),
    };

    return NetworkRecapLayout::pages(SampleDeck::recap($clubs, $discount, $longNames, $folder), $folder.'/sfondo.png')[$page];
}

/**
 * The PNG reduced to 960×540, the size of the comparison (an image already 960×540 is kept as is).
 */
function visualReduced(string $png): GdImage
{
    $image = imagecreatefrompng($png);

    if ($image !== false && imagesx($image) === VISUAL_WIDTH && imagesy($image) === VISUAL_HEIGHT) {
        return $image;
    }

    $reduced = $image === false ? false : imagescale($image, VISUAL_WIDTH, VISUAL_HEIGHT, IMG_BICUBIC);

    if ($reduced === false) {
        throw new RuntimeException("Cannot read the image {$png}.");
    }

    return $reduced;
}

/**
 * Share of different pixels between two images of the same size, and the map of the differences.
 *
 * @return array{float, GdImage}
 */
function visualDifference(GdImage $actual, GdImage $reference): array
{
    $map = imagecreatetruecolor(VISUAL_WIDTH, VISUAL_HEIGHT);
    $red = (int) imagecolorallocate($map, 255, 0, 0);
    $different = 0;

    for ($y = 0; $y < VISUAL_HEIGHT; $y++) {
        for ($x = 0; $x < VISUAL_WIDTH; $x++) {
            $a = imagecolorat($actual, $x, $y);
            $b = imagecolorat($reference, $x, $y);
            $delta = max(
                abs(($a >> 16 & 0xFF) - ($b >> 16 & 0xFF)),
                abs(($a >> 8 & 0xFF) - ($b >> 8 & 0xFF)),
                abs(($a & 0xFF) - ($b & 0xFF)),
            );

            if ($delta > VISUAL_CHANNEL_TOLERANCE) {
                $different++;
                imagesetpixel($map, $x, $y, $red);
            } else {
                $grey = (int) (((($b >> 16 & 0xFF) + ($b >> 8 & 0xFF) + ($b & 0xFF)) / 3) * 0.3 + 178);
                imagesetpixel($map, $x, $y, (int) imagecolorallocate($map, $grey, $grey, $grey));
            }
        }
    }

    return [$different / (VISUAL_WIDTH * VISUAL_HEIGHT), $map];
}

beforeEach(function () {
    $this->visualFolder = storage_path('framework/testing/visual');
    File::ensureDirectoryExists($this->visualFolder.'/sources');
});

it('layout §6.5 (D-311): the rendering matches the approved reference', function (string $case) {
    $folder = $this->visualFolder;
    $actualPng = "{$folder}/{$case}-full.png";
    $reference = base_path("tests/Pdf/references/{$case}.png");

    app(PdfPipeline::class)->screenshot(visualCasePage($case, $folder.'/sources'), $actualPng);
    $actual = visualReduced($actualPng);

    if (! is_file($reference)) {
        if (env('VISUAL_REFERENCES_WRITE_MISSING') === true || env('VISUAL_REFERENCES_WRITE_MISSING') === 'true') {
            File::ensureDirectoryExists(dirname($reference));

            if ($case === 'static_slide') {
                imagepng($actual, $reference, 9);
            } else {
                copy($actualPng, $reference);
            }

            fwrite(STDOUT, "\n      Riferimento creato: tests/Pdf/references/{$case}.png\n");
            expect(is_file($reference))->toBeTrue();

            return;
        }

        imagepng($actual, "{$folder}/{$case}-actual.png");
        $this->fail("Missing reference tests/Pdf/references/{$case}.png: the CI job \"pdf\" creates it on a pull request. Actual image: {$folder}/{$case}-actual.png");
    }

    [$share, $map] = visualDifference($actual, visualReduced($reference));

    if ($share > VISUAL_MAX_DIFFERENT) {
        imagepng($actual, "{$folder}/{$case}-actual.png");
        imagepng($map, "{$folder}/{$case}-diff.png");
    }

    expect($share)->toBeLessThanOrEqual(
        VISUAL_MAX_DIFFERENT,
        sprintf('%s: %.2f%% of the pixels differ from the reference (actual and diff in %s)', $case, $share * 100, $folder),
    );
})->with([
    'recap_two_clubs',
    'recap_discount_hospitality',
    'recap_twelve_clubs_page_1',
    'recap_twelve_clubs_page_2',
    'recap_long_names',
    'static_slide',
]);
