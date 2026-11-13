<?php

namespace App\Support\Demo;

use App\Domain\Documents\Layouts\CoverLayout;
use GdImage;
use RuntimeException;

/**
 * Pictures of the demo drawn with GD (D-438; DM-08): covers, light backgrounds of the recap and of
 * the composed slide, static slides of the library, club and client logos. They follow the style of
 * the network template (light page, coloured pill and title in capitals at the top left, corner at
 * the bottom right) and leave free the areas where the layouts write: the box of the client logo of
 * the cover (CoverLayout), the recap table from y 226 (network) or 453 (club) and the area of the
 * composed slide (x 56, y 226, 1790 × 690, layout-dinamici §4.11). A small note says that the
 * graphics are only for the demo. Every drawing is deterministic.
 */
final class DemoArtwork
{
    public const WIDTH = 1920;

    public const HEIGHT = 1080;

    /** Red of the network template (layout-dinamici §2.3). */
    public const RED = [204, 0, 51];

    private const NAVY = [11, 31, 58];

    private const NOTE = 'Grafica dimostrativa';

    /**
     * @param  array<int, string>  $fonts  CSS weight => TrueType file (400, 700, 900); empty = GD font
     */
    public function __construct(private readonly array $fonts = []) {}

    /**
     * Cover: dark band at the top for the white date (CoverLayout), title of the season on the
     * coloured page and a white panel behind the box of the client logo.
     *
     * @param  'network'|'club'  $type
     * @param  array{int, int, int}  $rgb
     */
    public function cover(string $type, string $title, string $subtitle, array $rgb): string
    {
        $image = $this->canvas($rgb);
        $text = $this->onColor($rgb);
        imagefilledrectangle($image, 0, 0, self::WIDTH - 1, 62, $this->color($image, [17, 17, 17]));
        $this->text($image, 'INFRONT', 56, 44, 26, [255, 255, 255], 900);

        [$left, $top, $width, $height] = CoverLayout::LOGO_BOXES[$type];
        $titleLeft = $type === 'club' ? 640 : 560;
        $lines = $this->title($image, $title, $titleLeft, 300, 96, $text);
        $this->text($image, $subtitle, $titleLeft, 300 + (int) ($lines * 96 * 1.1) - 20, 40, $text, 700);
        imagefilledrectangle($image, $left - 24, $top - 24, $left + $width + 24, $top + $height + 24, $this->color($image, [255, 255, 255]));
        imagefilledpolygon($image, [1700, self::HEIGHT - 1, self::WIDTH - 1, 820, self::WIDTH - 1, self::HEIGHT - 1], $this->color($image, self::RED));
        $this->note($image, $text);

        return $this->png($image);
    }

    /**
     * Light background of the recap or of the composed slide: page 246 grey, pill in the accent
     * colour and title at the top left, corner at the bottom right (the club recap already heads its
     * table with the name of the club, D-415).
     *
     * @param  array{int, int, int}  $accent
     */
    public function light(string $title, array $accent): string
    {
        $image = $this->canvas([246, 246, 246]);
        $color = $this->color($image, $accent);
        imagefilledrectangle($image, 89, 78, 162, 104, $color);
        imagefilledellipse($image, 89, 91, 26, 26, $color);
        imagefilledellipse($image, 162, 91, 26, 26, $color);
        $this->text($image, mb_strtoupper($title), 76, 182, 50, [17, 17, 17], 900);

        imagefilledpolygon($image, [1845, self::HEIGHT - 1, self::WIDTH - 1, 985, self::WIDTH - 1, self::HEIGHT - 1], $color);
        $this->note($image, [120, 120, 120]);

        return $this->png($image);
    }

    /**
     * Static slide of the library: a page in the colour of the club or of Infront with a lighter
     * band, the title and a line of text.
     *
     * @param  array{int, int, int}  $rgb
     */
    public function slide(string $title, string $text, array $rgb): string
    {
        $image = $this->canvas($rgb);

        for ($step = 0; $step < 12; $step++) {
            $shade = [min(255, $rgb[0] + 8 * $step), min(255, $rgb[1] + 8 * $step), min(255, $rgb[2] + 8 * $step)];
            imagefilledrectangle($image, 1100 + 70 * $step, 0, self::WIDTH - 1, self::HEIGHT - 1, $this->color($image, $shade));
        }

        $color = $this->onColor($rgb);
        imagefilledrectangle($image, 80, 360, 230, 372, $this->color($image, $color));
        $this->title($image, mb_strtoupper($title), 80, 500, 88, $color);
        $this->text($image, $text, 80, 600, 36, $color, 400);
        $this->note($image, $color);

        return $this->png($image);
    }

    /**
     * Club logo: disc in the primary colour with the initials, transparent around it (D-439, used
     * when the real logo is not in the folder of the demo).
     *
     * @param  array{int, int, int}  $rgb
     */
    public function clubLogo(string $initials, array $rgb): string
    {
        $image = $this->transparent(400, 400);
        imagefilledellipse($image, 200, 200, 392, 392, $this->color($image, $rgb));
        imageellipse($image, 200, 200, 392, 392, $this->color($image, [255, 255, 255]));
        $this->text($image, $initials, strlen($initials) > 2 ? 60 : 90, 245, 120, $this->onColor($rgb), 900);

        return $this->png($image);
    }

    /**
     * Client logo with a full background: the LED strip takes its colour (D-425).
     *
     * @param  array{int, int, int}  $background
     * @param  array{int, int, int}  $text
     */
    public function opaqueLogo(string $name, array $background, array $text): string
    {
        $name = mb_strtoupper($name);
        $image = $this->image($this->textWidth($name, 110) + 120, 260);
        imagefill($image, 0, 0, $this->color($image, $background));
        $this->text($image, $name, 60, 170, 110, $text, 900);

        return $this->png($image);
    }

    /**
     * Client logo on a transparent background: the LED strip is black (D-425).
     *
     * @param  array{int, int, int}  $rgb
     */
    public function transparentLogo(string $name, array $rgb): string
    {
        $name = mb_strtoupper($name);
        $image = $this->transparent($this->textWidth($name, 100) + 290, 260);
        imagefilledellipse($image, 120, 130, 200, 200, $this->color($image, $rgb));
        $this->text($image, $name, 250, 170, 100, $rgb, 900);

        return $this->png($image);
    }

    /**
     * Photo-like field for the LED simulation when the photos of the demo are missing: 1768×726 like
     * the photos of the old system, with the two rows at y 180–218 and 224–262 (the measured areas of
     * the generic Infront photos, DemoData::FIELD_AREAS).
     */
    public function fieldPhoto(): string
    {
        $image = $this->image(1768, 726);
        imagefill($image, 0, 0, $this->color($image, [70, 74, 84]));

        for ($y = 10; $y < 170; $y += 22) {
            for ($x = intdiv($y, 22) % 2 * 11; $x < 1768; $x += 22) {
                imagefilledellipse($image, $x, $y, 14, 14, $this->color($image, [90 + ($x % 70), 92, 104]));
            }
        }

        imagefilledrectangle($image, 0, 170, 1767, 179, $this->color($image, [120, 124, 130]));
        imagefilledrectangle($image, 0, 180, 1767, 218, $this->color($image, [228, 228, 228]));
        imagefilledrectangle($image, 0, 219, 1767, 223, $this->color($image, [40, 44, 52]));
        imagefilledrectangle($image, 0, 224, 1767, 262, $this->color($image, [222, 222, 222]));

        for ($x = 0, $stripe = 0; $x < 1768; $x += 160, $stripe++) {
            imagefilledrectangle($image, $x, 263, min(1767, $x + 159), 725, $this->color($image, $stripe % 2 === 0 ? [46, 140, 52] : [56, 156, 62]));
        }

        return $this->jpeg($image);
    }

    /**
     * Picture for the composed slide of the demo (an image block): a simple chart-like drawing.
     */
    public function chart(): string
    {
        $image = $this->image(800, 450);
        imagefill($image, 0, 0, $this->color($image, [255, 255, 255]));
        $bars = [180, 260, 220, 330, 390];

        foreach ($bars as $index => $height) {
            imagefilledrectangle($image, 80 + 140 * $index, 420 - $height, 170 + 140 * $index, 420, $this->color($image, $index === 4 ? self::RED : self::NAVY));
        }

        imageline($image, 60, 420, 760, 420, $this->color($image, [60, 60, 60]));

        return $this->png($image);
    }

    /**
     * Text colour readable on a background: dark on light colours, white on dark ones.
     *
     * @param  array{int, int, int}  $rgb
     * @return array{int, int, int}
     */
    public function onColor(array $rgb): array
    {
        $luma = 0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2];

        return $luma > 160 ? [17, 17, 17] : [255, 255, 255];
    }

    /**
     * "#87D8F7" → [135, 216, 247]; the navy of Infront without a colour.
     *
     * @return array{int, int, int}
     */
    public static function rgb(?string $hex): array
    {
        if ($hex === null || preg_match('/^#([0-9A-F]{2})([0-9A-F]{2})([0-9A-F]{2})$/i', $hex, $match) !== 1) {
            return self::NAVY;
        }

        return [(int) hexdec($match[1]), (int) hexdec($match[2]), (int) hexdec($match[3])];
    }

    /**
     * @return array{int, int, int}
     */
    public static function navy(): array
    {
        return self::NAVY;
    }

    /**
     * A title on up to two lines, split at the last space before 18 characters; returns the lines.
     *
     * @param  array{int, int, int}  $rgb
     */
    private function title(GdImage $image, string $title, int $x, int $y, int $size, array $rgb): int
    {
        $lines = array_slice(explode("\n", wordwrap($title, 18, "\n")), 0, 2);

        foreach ($lines as $index => $line) {
            $this->text($image, $line, $x, $y + (int) ($index * $size * 1.1), $size, $rgb, 900);
        }

        return count($lines);
    }

    /**
     * @param  array{int, int, int}  $rgb
     */
    private function note(GdImage $image, array $rgb): void
    {
        $this->text($image, self::NOTE, 56, self::HEIGHT - 24, 16, $rgb, 400);
    }

    /**
     * @param  array{int, int, int}  $rgb
     */
    private function canvas(array $rgb): GdImage
    {
        $image = $this->image(self::WIDTH, self::HEIGHT);
        imagefill($image, 0, 0, $this->color($image, $rgb));

        return $image;
    }

    private function transparent(int $width, int $height): GdImage
    {
        $image = $this->image($width, $height);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);

        return $image;
    }

    private function image(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));

        if ($image === false) {
            throw new RuntimeException('GD cannot create the demo image.');
        }

        return $image;
    }

    /**
     * @param  array{int, int, int}  $rgb
     */
    private function color(GdImage $image, array $rgb): int
    {
        return (int) imagecolorallocate($image, max(0, min(255, $rgb[0])), max(0, min(255, $rgb[1])), max(0, min(255, $rgb[2])));
    }

    /**
     * Text with the font of the weight (Interstate on the VM, DejaVu in tests), or GD's font.
     *
     * @param  array{int, int, int}  $rgb
     */
    private function text(GdImage $image, string $text, int $x, int $y, int $size, array $rgb, int $weight = 700): void
    {
        $font = $this->fonts[$weight] ?? null;

        if (is_string($font) && is_file($font) && function_exists('imagettftext')) {
            imagettftext($image, $size * 0.75, 0, $x, $y, $this->color($image, $rgb), $font, $text);

            return;
        }

        imagestring($image, 5, $x, $y - 15, $text, $this->color($image, $rgb));
    }

    /**
     * Width in pixels of a text drawn by text() (the GD font is 9 pixels per character).
     */
    private function textWidth(string $text, int $size, int $weight = 900): int
    {
        $font = $this->fonts[$weight] ?? null;

        if (is_string($font) && is_file($font) && function_exists('imagettfbbox')) {
            $box = imagettfbbox($size * 0.75, 0, $font, $text);

            if ($box !== false) {
                return abs($box[2] - $box[0]);
            }
        }

        return 9 * mb_strlen($text);
    }

    private function png(GdImage $image): string
    {
        ob_start();
        imagepng($image, null, 6);
        $png = (string) ob_get_clean();

        if ($png === '') {
            throw new RuntimeException('GD could not encode the demo image.');
        }

        return $png;
    }

    private function jpeg(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 88);

        return (string) ob_get_clean();
    }
}
