<?php

namespace App\Domain\Documents\Services;

use GdImage;

/**
 * Colour of the strip of the LED behind the logo of the client (D-398, D-425; DM-06): the colour that
 * prevails on the edges of the logo, so the logo never shows a box of another colour around it;
 * black when the edges are mostly transparent (a logo without its own background) or the image
 * cannot be read.
 *
 * The pixels of the four edges are grouped by colour with a tolerance (16 levels per channel) and
 * the average of the largest group is returned as #RRGGBB.
 */
final class LogoStripColor
{
    public const BLACK = '#000000';

    /** Edge pixels read on every side at most (enough for the prevailing colour). */
    private const SAMPLES_PER_SIDE = 200;

    /** A pixel counts as transparent above this GD alpha (0 opaque … 127 transparent). */
    private const TRANSPARENT_ALPHA = 96;

    public static function of(string $path): string
    {
        $contents = @file_get_contents($path);
        $image = $contents !== false ? @imagecreatefromstring($contents) : false;

        if (! $image instanceof GdImage) {
            return self::BLACK;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $pixels = [];

        foreach (self::positions($width) as $x) {
            $pixels[] = [$x, 0];
            $pixels[] = [$x, $height - 1];
        }

        foreach (self::positions($height) as $y) {
            $pixels[] = [0, $y];
            $pixels[] = [$width - 1, $y];
        }

        $transparent = 0;
        $groups = [];

        foreach ($pixels as [$x, $y]) {
            $index = imagecolorat($image, $x, $y);

            if ($index === false) {
                continue;
            }

            $color = imagecolorsforindex($image, $index);

            if ($color['alpha'] > self::TRANSPARENT_ALPHA) {
                $transparent++;

                continue;
            }

            $key = intdiv($color['red'], 16).'-'.intdiv($color['green'], 16).'-'.intdiv($color['blue'], 16);
            $groups[$key] ??= [0, 0, 0, 0];
            $groups[$key][0]++;
            $groups[$key][1] += $color['red'];
            $groups[$key][2] += $color['green'];
            $groups[$key][3] += $color['blue'];
        }

        if ($groups === [] || $transparent * 2 > count($pixels)) {
            return self::BLACK;
        }

        usort($groups, fn (array $a, array $b): int => $b[0] <=> $a[0]);
        [$count, $red, $green, $blue] = $groups[0];

        return sprintf('#%02X%02X%02X', intdiv($red, $count), intdiv($green, $count), intdiv($blue, $count));
    }

    /**
     * @return list<int>
     */
    private static function positions(int $length): array
    {
        $step = max(1, intdiv($length, self::SAMPLES_PER_SIDE));

        return range(0, $length - 1, $step);
    }
}
