<?php

namespace App\Support\Documents;

use App\Domain\Documents\Data\DocumentPage;
use App\Domain\Documents\Data\NetworkRecap;
use App\Domain\Documents\Data\RecapRow;
use App\Domain\Documents\Data\RecapTotals;
use App\Domain\Documents\Data\StaticSlidePage;
use App\Domain\Documents\Layouts\NetworkRecapLayout;
use App\Support\Text;
use GdImage;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Fictitious material of the PDF proof (fetta S1, D-309): backgrounds, static slides and club logos
 * drawn with GD, recap data with invented clubs and prices. Nothing real enters the repository;
 * on the VM "documents:try --samples" can use real images from DOCUMENT_SAMPLES_PATH instead.
 *
 * Every drawing is deterministic (fixed seeds), so the visual regression tests compare the same
 * pictures in CI and on the VM.
 */
final class SampleDeck
{
    public const DISCOUNT_NONE = 'none';

    public const DISCOUNT_PERCENT = 'percent';

    public const DISCOUNT_AMOUNT = 'amount';

    /**
     * Invented clubs: name, initials and colour of the logo.
     *
     * @var list<array{string, string, array{int, int, int}}>
     */
    private const CLUBS = [
        ['Atletico Borgoverde', 'AB', [0, 122, 61]],
        ['Sporting Valdarno', 'SV', [200, 16, 46]],
        ['Unione Calcio Lagomaggiore', 'UL', [0, 61, 165]],
        ['Real Colle Alto', 'RC', [255, 184, 28]],
        ['Polisportiva Riva Blu', 'PR', [0, 140, 200]],
        ['Virtus Montefosco', 'VM', [90, 40, 130]],
        ['Calcio Pianalto', 'CP', [30, 30, 30]],
        ['Dinamo Portosole', 'DP', [230, 90, 20]],
        ['Audace Valtorre', 'AV', [120, 120, 120]],
        // Accented name: lets Antonio check à, è, ò with Interstate (D-311).
        ['Società Sportiva Città di Castellò', 'SC', [10, 90, 70]],
        ['Fortitudo Mareluce', 'FM', [180, 20, 90]],
        ['Robur Serravecchia', 'RS', [60, 100, 160]],
    ];

    private const LONG_NAME = 'Associazione Sportiva Dilettantistica Unione Calcio Borgoverde e Valle del Torrente Alto';

    /**
     * Fictitious recap: $clubs clubs (1-12), every third one with hospitality, optional discount and
     * a very long club name to try the fitting of the texts.
     */
    public static function recap(int $clubs, string $discount = self::DISCOUNT_NONE, bool $longNames = false, ?string $logosFolder = null): NetworkRecap
    {
        // Amounts in cents (whole numbers) inside the sample, written as decimal strings (C5).
        $rows = [];
        $listCents = 0;
        $matchesTotal = 0;
        $secondsTotal = 0;
        $spotsTotal = 0;
        $formats = ['TOP PARTNER · 1ª fila', 'PREMIUM PARTNER · Jumbo', '1ª fila', 'Domination', null];

        foreach (array_slice(self::CLUBS, 0, max(1, min($clubs, count(self::CLUBS)))) as $index => [$name, $initials, $color]) {
            $format = $formats[$index % count($formats)];
            $seconds = $format === null ? null : [60, 90, 120, 150][$index % 4];
            $matches = $format === null ? null : 19;
            $spots = $seconds === null ? null : intdiv($seconds, 15);
            $priceCents = $format === null ? 4_500_000 : (38_000 + 2_500 * $index) * 100;

            $logo = null;

            if ($logosFolder !== null) {
                $logo = $logosFolder.'/logo-'.strtolower($initials).'.png';
                file_put_contents($logo, self::logo($initials, $color));
            }

            $rows[] = new RecapRow(
                label: $longNames && $index === 0 ? self::LONG_NAME : $name,
                logoPath: $logo,
                format: $format === null ? Text::get('documents.network_recap.none') : ($longNames && $index === 0 ? $format.' · Ledwall con animazioni dedicate' : $format),
                matches: $matches,
                secondsPerMatch: $seconds,
                spotsPerMatch: $spots,
                listPrice: self::decimal($priceCents),
            );
            $listCents += $priceCents;

            if ($matches !== null && $spots !== null) {
                $matchesTotal += $matches;
                $secondsTotal += $seconds * $matches;
                $spotsTotal += $spots * $matches;
            }

            if ($index % 3 === 1) {
                $seats = 2 + $index % 3;
                $hospitalityCents = 150_000 * $seats;
                $rows[] = new RecapRow(
                    label: 'Hospitality Premium × '.$seats,
                    logoPath: null,
                    format: '',
                    matches: null,
                    secondsPerMatch: null,
                    spotsPerMatch: null,
                    listPrice: self::decimal($hospitalityCents),
                    isHospitality: true,
                );
                $listCents += $hospitalityCents;
            }
        }

        // 15% rounded half up to the cent (sample data only: the real discount is slice F5).
        [$label, $finalCents] = match ($discount) {
            self::DISCOUNT_PERCENT => [
                Text::get('documents.network_recap.discount_percent', ['value' => '15']),
                $listCents - intdiv($listCents * 15 + 50, 100),
            ],
            self::DISCOUNT_AMOUNT => [
                Text::get('documents.network_recap.discount_amount', ['value' => DocumentNumbers::amount('10000.00')]),
                $listCents - 1_000_000,
            ],
            default => [null, $listCents],
        };

        return new NetworkRecap(
            $rows,
            new RecapTotals($matchesTotal, $secondsTotal, $spotsTotal, self::decimal($listCents), $label, self::decimal($finalCents)),
            $clubs > 6 ? 'Le squadre retrocesse a fine stagione non sono incluse nel calcolo delle partite della stagione successiva.' : null,
        );
    }

    /**
     * A deck of $pages pages for documents:try and the M09-CA4 test (about 40 slides): a cover, the
     * recap of 12 clubs (two pages, with the continuation) and static slides up to the total, each
     * with its own picture. The images are written into $folder; with $samples the real images of
     * that folder are used instead, repeated if they are fewer than the pages.
     *
     * @return list<DocumentPage>
     */
    public static function deck(string $folder, int $pages = 40, ?string $samples = null): array
    {
        File::ensureDirectoryExists($folder);

        $images = $samples !== null ? self::sampleImages($samples) : [];
        $background = null;

        foreach ($images as $key => $image) {
            if (str_starts_with(strtolower(basename($image)), 'recap')) {
                $background = $image;
                unset($images[$key]);
            }
        }

        $images = array_values($images);

        if ($background === null) {
            $background = $folder.'/recap-background.png';
            file_put_contents($background, self::background('Proposta economica'));
        }

        // One different picture for each static slide, so the weight of the PDF is realistic
        // (identical images would be stored only once in the file).
        if ($images === []) {
            for ($index = 1; $index <= $pages; $index++) {
                $images[] = $path = $folder.sprintf('/slide-%02d.png', $index);
                file_put_contents($path, self::staticSlide($index));
            }
        }

        $recapPages = NetworkRecapLayout::pages(self::recap(12, self::DISCOUNT_PERCENT, false, $folder), $background);
        $deck = [new StaticSlidePage($images[0])];
        $static = max(0, $pages - 1 - count($recapPages));

        for ($index = 1; $index <= $static; $index++) {
            $deck[] = new StaticSlidePage($images[$index % count($images)]);

            // The recap sits towards the end, as in the real documents.
            if ($index === $static - 2) {
                $deck = [...$deck, ...$recapPages];
            }
        }

        if (count($deck) < $pages) {
            $deck = [...$deck, ...$recapPages];
        }

        return array_slice($deck, 0, $pages);
    }

    /**
     * Background of a layout: white page, red band with the title at the top, thin footer line.
     */
    public static function background(string $title): string
    {
        $image = self::canvas(1920, 1080, [255, 255, 255]);
        imagefilledrectangle($image, 0, 0, 1919, 150, self::color($image, [204, 0, 51]));
        self::text($image, $title, 56, 100, 44, [255, 255, 255]);
        imagefilledrectangle($image, 56, 1020, 1863, 1022, self::color($image, [204, 0, 51]));
        self::text($image, 'Documento di prova con dati fittizi', 56, 1060, 16, [120, 120, 120]);

        return self::png($image);
    }

    /**
     * A photo-like static slide: gradient and many soft shapes, so that its JPEG weighs about as
     * much as a real slide (150-300 KB).
     */
    public static function staticSlide(int $number): string
    {
        mt_srand(1000 + $number);
        $image = self::image(1920, 1080);
        [$r, $g, $b] = [mt_rand(20, 120), mt_rand(20, 120), mt_rand(40, 160)];

        for ($y = 0; $y < 1080; $y += 4) {
            $shade = (int) ($y / 1080 * 90);
            imagefilledrectangle($image, 0, $y, 1919, $y + 3, self::color($image, [min(255, $r + $shade), min(255, $g + $shade), min(255, $b + $shade)]));
        }

        for ($index = 0; $index < 2500; $index++) {
            $size = mt_rand(6, 90);
            imagefilledellipse(
                $image,
                mt_rand(0, 1919),
                mt_rand(0, 1079),
                $size,
                $size,
                (int) imagecolorallocatealpha($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255), mt_rand(60, 115)),
            );
        }

        imagefilledrectangle($image, 0, 860, 1919, 1079, (int) imagecolorallocatealpha($image, 0, 0, 0, 50));
        self::text($image, 'Slide statica di prova '.$number, 80, 980, 56, [255, 255, 255]);
        mt_srand();

        return self::png($image);
    }

    /**
     * Club logo: coloured disc with the initials, transparent around it.
     *
     * @param  array{int, int, int}  $rgb
     */
    public static function logo(string $initials, array $rgb): string
    {
        $image = self::image(200, 200);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);
        imagefilledellipse($image, 100, 100, 196, 196, self::color($image, $rgb));
        self::text($image, $initials, 38, 125, 64, [255, 255, 255]);

        return self::png($image);
    }

    /**
     * Images of the samples folder: JPEG or PNG of 1920×1080, sorted by name.
     *
     * @return list<string>
     */
    public static function sampleImages(string $folder): array
    {
        $images = [];

        foreach (glob(rtrim($folder, '/').'/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE) ?: [] as $path) {
            $size = @getimagesize($path);

            if (is_array($size) && $size[0] === 1920 && $size[1] === 1080) {
                $images[] = $path;
            }
        }

        sort($images);

        return $images;
    }

    /**
     * Cents as a decimal string: 4500000 → "45000.00".
     */
    private static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array{int, int, int}  $rgb
     */
    private static function canvas(int $width, int $height, array $rgb): GdImage
    {
        $image = self::image($width, $height);
        imagefill($image, 0, 0, self::color($image, $rgb));

        return $image;
    }

    private static function image(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));

        if ($image === false) {
            throw new RuntimeException('GD cannot create the sample image.');
        }

        return $image;
    }

    /**
     * @param  array{int, int, int}  $rgb
     */
    private static function color(GdImage $image, array $rgb): int
    {
        return (int) imagecolorallocate(
            $image,
            max(0, min(255, $rgb[0])),
            max(0, min(255, $rgb[1])),
            max(0, min(255, $rgb[2])),
        );
    }

    /**
     * Text drawn with DejaVu Bold when available (deterministic), otherwise with GD's built-in font.
     *
     * @param  array{int, int, int}  $rgb
     */
    private static function text(GdImage $image, string $text, int $x, int $y, int $size, array $rgb): void
    {
        $font = config('documents.substitute_font_files.700');

        if (is_string($font) && is_file($font) && function_exists('imagettftext')) {
            imagettftext($image, $size * 0.75, 0, $x, $y, self::color($image, $rgb), $font, $text);

            return;
        }

        imagestring($image, 5, $x, $y - 15, $text, self::color($image, $rgb));
    }

    private static function png(GdImage $image): string
    {
        ob_start();
        imagepng($image, null, 6);
        $png = (string) ob_get_clean();

        if ($png === '') {
            throw new RuntimeException('GD could not encode the sample image.');
        }

        return $png;
    }
}
