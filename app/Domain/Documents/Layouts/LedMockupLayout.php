<?php

namespace App\Domain\Documents\Layouts;

use App\Domain\Documents\Data\LayoutPage;
use App\Enums\DocumentLayout;
use Closure;
use InvalidArgumentException;

/**
 * Simulation of the logo of the client on the LED of a field (D-398, D-423…D-425; DM-06): the photo
 * of the field on the whole page (scaled to cover 1920×1080, centred, D-424) and, on every area of
 * the LED marked on the photo, a strip in the colour of the background of the logo (black for a
 * transparent logo, D-425) with the logo repeated at the chosen spacing, the first one cut in half
 * like in the old system.
 *
 * - "rows" (1ª fila, 2ª fila, Jumbo): one strip per area, the logo at logo_height_percent of the
 *   height of the area;
 * - "domination": one strip over both areas (from the top of the upper one to the bottom of the
 *   lower one) with a larger logo and the grey separator line (66, 66, 66) between the two rows.
 *
 * Every strip is a rectangle of the length of the top edge of its area and the height of its left
 * edge, brought onto the four points with a projective transform (CSS matrix3d computed here from
 * the points, so the perspective of the photo is kept). The page has no background image: the
 * photo is part of the layout.
 */
final class LedMockupLayout
{
    public const STYLE_ROWS = 'rows';

    public const STYLE_DOMINATION = 'domination';

    /** Separator line of the Domination, as in the old system. */
    public const LINE_COLOR = '#424242';

    /** Thickness of the separator line in % of the height of the strip (9 px on about 82 px). */
    public const LINE_PERCENT = 11;

    private const PAGE_WIDTH = 1920;

    private const PAGE_HEIGHT = 1080;

    /**
     * @param  list<list<array{int|float, int|float}>>  $areas  four points each, pixels of the photo
     */
    public static function page(
        string $photoPath,
        int $photoWidth,
        int $photoHeight,
        array $areas,
        string $style,
        string $logoPath,
        int $logoWidth,
        int $logoHeight,
        string $color,
        int $logoHeightPercent,
        int $spacing,
    ): LayoutPage {
        return new LayoutPage(DocumentLayout::LedMockup, null, [
            'photo' => $photoPath,
            'photo_width' => $photoWidth,
            'photo_height' => $photoHeight,
            'areas' => $areas,
            'style' => $style,
            'logo' => $logoPath,
            'logo_width' => $logoWidth,
            'logo_height' => $logoHeight,
            'color' => $color,
            'logo_height_percent' => $logoHeightPercent,
            'spacing' => $spacing,
        ]);
    }

    /**
     * The photo frame and the strips for the view documents.layouts.led-mockup.
     *
     * @param  array<string, mixed>  $data
     * @param  Closure(string): string  $asset
     * @return array{frame: array<string, string>, photo: string, strips: list<array<string, mixed>>}
     */
    public static function viewData(array $data, Closure $asset): array
    {
        $photoWidth = self::int($data['photo_width'] ?? 0);
        $photoHeight = self::int($data['photo_height'] ?? 0);

        if ($photoWidth < 1 || $photoHeight < 1) {
            throw new InvalidArgumentException('The photo of the LED simulation has no size.');
        }

        $scale = max(self::PAGE_WIDTH / $photoWidth, self::PAGE_HEIGHT / $photoHeight);
        $logo = $asset((string) ($data['logo'] ?? ''));
        $ratio = self::int($data['logo_height'] ?? 0) > 0 ? self::int($data['logo_width'] ?? 0) / self::int($data['logo_height'] ?? 1) : 1.0;
        $percent = max(10, min(100, self::int($data['logo_height_percent'] ?? 85)));
        $spacing = max(0, self::int($data['spacing'] ?? 0));
        $color = self::color((string) ($data['color'] ?? ''));
        $areas = self::areas($data['areas'] ?? []);
        $strips = [];

        if (($data['style'] ?? self::STYLE_ROWS) === self::STYLE_DOMINATION && count($areas) === 2) {
            [$upper, $lower] = $areas;
            $quad = [$upper[0], $upper[1], $lower[2], $lower[3]];
            $strip = self::strip($quad, $logo, $ratio, $percent, $spacing, $color);
            $top = $upper[0][1];
            $height = $lower[3][1] - $top;
            $boundary = ($upper[3][1] + $lower[0][1]) / 2;
            $stripHeight = (float) $strip['height'];
            $thickness = max(2.0, $stripHeight * self::LINE_PERCENT / 100);
            $middle = $height > 0 ? ($boundary - $top) / $height * $stripHeight : $stripHeight / 2;
            $strip['line'] = ['top' => self::number($middle - $thickness / 2), 'height' => self::number($thickness), 'color' => self::LINE_COLOR];
            $strips[] = $strip;
        } else {
            foreach ($areas as $quad) {
                $strips[] = self::strip($quad, $logo, $ratio, $percent, $spacing, $color);
            }
        }

        return [
            'frame' => [
                'left' => self::number((self::PAGE_WIDTH - $photoWidth * $scale) / 2),
                'top' => self::number((self::PAGE_HEIGHT - $photoHeight * $scale) / 2),
                'width' => (string) $photoWidth,
                'height' => (string) $photoHeight,
                'scale' => self::number($scale, 6),
            ],
            'photo' => $asset((string) ($data['photo'] ?? '')),
            'strips' => $strips,
        ];
    }

    /**
     * CSS matrix3d bringing a rectangle width × height (origin at its top left) onto the four points
     * (top left, top right, bottom right, bottom left): the projective transform of the unit square
     * onto a quadrilateral (Heckbert, "Fundamentals of texture mapping", 1989), scaled to the size.
     *
     * @param  list<array{float, float}>  $quad
     */
    public static function matrix3d(array $quad, float $width, float $height): string
    {
        [[$x0, $y0], [$x1, $y1], [$x2, $y2], [$x3, $y3]] = $quad;

        $dx1 = $x1 - $x2;
        $dx2 = $x3 - $x2;
        $dx3 = $x0 - $x1 + $x2 - $x3;
        $dy1 = $y1 - $y2;
        $dy2 = $y3 - $y2;
        $dy3 = $y0 - $y1 + $y2 - $y3;

        if (abs($dx3) < 1e-9 && abs($dy3) < 1e-9) {
            // Parallelogram: affine.
            [$g, $h] = [0.0, 0.0];
        } else {
            $det = $dx1 * $dy2 - $dx2 * $dy1;

            if (abs($det) < 1e-9) {
                throw new InvalidArgumentException('The four points of the LED do not make a quadrilateral.');
            }

            $g = ($dx3 * $dy2 - $dx2 * $dy3) / $det;
            $h = ($dx1 * $dy3 - $dx3 * $dy1) / $det;
        }

        $a = $x1 - $x0 + $g * $x1;
        $b = $x3 - $x0 + $h * $x3;
        $d = $y1 - $y0 + $g * $y1;
        $e = $y3 - $y0 + $h * $y3;

        // From the unit square to the size of the element: u = x / width, v = y / height.
        [$a, $d, $g] = [$a / $width, $d / $width, $g / $width];
        [$b, $e, $h] = [$b / $height, $e / $height, $h / $height];

        // Column-major 4×4 of [[a, b, 0, x0], [d, e, 0, y0], [0, 0, 1, 0], [g, h, 0, 1]].
        $values = [$a, $d, 0, $g, $b, $e, 0, $h, 0, 0, 1, 0, $x0, $y0, 0, 1];

        return 'matrix3d('.implode(',', array_map(fn (int|float $value): string => self::number((float) $value, 8), $values)).')';
    }

    /**
     * @param  list<array{float, float}>  $quad
     * @return array<string, mixed>
     */
    private static function strip(array $quad, string $logo, float $ratio, int $percent, int $spacing, string $color): array
    {
        $width = max(1.0, hypot($quad[1][0] - $quad[0][0], $quad[1][1] - $quad[0][1]));
        $height = max(1.0, hypot($quad[3][0] - $quad[0][0], $quad[3][1] - $quad[0][1]));
        $logoHeight = $height * $percent / 100;
        $logoWidth = max(1.0, $logoHeight * $ratio);
        $step = $logoWidth + $spacing;
        $logos = [];

        // The first logo is cut in half on the left edge, as in the old system.
        for ($x = -$logoWidth / 2, $count = 0; $x < $width && $count < 500; $x += $step, $count++) {
            $logos[] = [
                'left' => self::number($x),
                'top' => self::number(($height - $logoHeight) / 2),
                'width' => self::number($logoWidth),
                'height' => self::number($logoHeight),
            ];
        }

        return [
            'width' => self::number($width),
            'height' => self::number($height),
            'transform' => self::matrix3d($quad, $width, $height),
            'color' => $color,
            'logo' => $logo,
            'logos' => $logos,
            'line' => null,
        ];
    }

    /**
     * @return list<list<array{float, float}>>
     */
    private static function areas(mixed $areas): array
    {
        $result = [];

        foreach (is_array($areas) ? $areas : [] as $area) {
            if (! is_array($area) || count($area) !== 4) {
                throw new InvalidArgumentException('Every area of the LED has four points.');
            }

            $points = [];

            foreach (array_values($area) as $point) {
                if (! is_array($point) || ! is_numeric($point[0] ?? null) || ! is_numeric($point[1] ?? null)) {
                    throw new InvalidArgumentException('Every point of the LED has two numbers.');
                }

                $points[] = [(float) $point[0], (float) $point[1]];
            }

            $result[] = $points;
        }

        return $result;
    }

    private static function color(string $color): string
    {
        return preg_match('/^#[0-9A-F]{6}$/i', $color) === 1 ? strtoupper($color) : '#000000';
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function number(float $value, int $decimals = 3): string
    {
        $text = rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');

        return $text === '-0' || $text === '' ? '0' : $text;
    }
}
