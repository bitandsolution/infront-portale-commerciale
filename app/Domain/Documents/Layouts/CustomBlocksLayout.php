<?php

namespace App\Domain\Documents\Layouts;

use App\Domain\Content\Data\CustomParagraph;
use App\Domain\Content\Data\CustomSlide;
use App\Domain\Content\Data\CustomText;
use App\Domain\Content\Services\CustomSlideContent;
use App\Domain\Documents\Data\CustomArrangement;
use App\Domain\Documents\Data\LayoutPage;
use App\Domain\Documents\Services\TextFitter;
use App\Enums\CustomBlockType;
use App\Enums\CustomSlideLayout;
use App\Enums\DocumentFontWeight;
use App\Enums\DocumentLayout;
use Closure;

/**
 * Slide composed with blocks (D-399, D-428…D-431; DM-07; layout-dinamici §4.11): the blocks written by
 * the agent drawn in a fixed content area of the background "Slide personalizzata" of the library.
 *
 * - Layouts: one column on the whole area; two columns of the same width (every block in its own);
 *   the image of the slide on the left (42% of the area, never cut) and the blocks on the right.
 * - Blocks stacked from the top: title (Interstate Black), text (Regular, bold, bulleted items),
 *   table (header in the red of the recaps, the cells fitted on one line like the recap,
 *   layout-dinamici §6.3), image (whole width of the column; at most 420 px high with one column,
 *   up to the height of the area with two columns or the image on the left), image with text
 *   (the image on 38% of the column).
 * - Too much content (D-431): everything is reduced together, in steps of 5%, down to 70%; beyond
 *   that what does not fit is cut at the bottom of the area: the last line kept ends with "…", an
 *   image is reduced to the space left (dropped below 80 px), table rows and later blocks are left
 *   out. The arrangement says whether something was cut, so the step "Documento" can warn.
 *
 * The widths are measured on the server with the font of the PDF (TextFitter), as for the other
 * layouts: the pages have no JavaScript (D-312). The same arrangement serves the PDF, the preview and
 * the warning, so they never disagree.
 */
final class CustomBlocksLayout
{
    /** Content area [left, top, width, height]: the background leaves it free (D-428). */
    public const AREA = [56, 226, 1790, 690];

    public const COLUMN_GAP = 60;

    /** Layout "image_text": share of the area for the image of the slide. */
    public const SLIDE_IMAGE_SHARE = 0.42;

    public const BLOCK_GAP = 28;

    public const TITLE_SIZE = 52;

    public const TITLE_LINE = 1.15;

    public const TITLE_COLOR = '#000000';

    public const TEXT_SIZE = 28;

    public const TEXT_LINE = 1.4;

    public const TEXT_COLOR = '#333333';

    /** Space between two paragraphs, and for an empty line, as a share of the size. */
    public const PARAGRAPH_GAP = 0.3;

    public const EMPTY_LINE = 0.7;

    /** Indent of the text of a bulleted item, as a share of the size. */
    public const BULLET_INDENT = 1.2;

    public const BULLET = '•';

    public const TABLE_SIZE = 24;

    /** Height of a table row and inner padding of a cell, as shares of the size. */
    public const TABLE_ROW = 2.2;

    public const TABLE_PADDING = 0.55;

    public const HEADER_BACKGROUND = '#CC0033';

    public const HEADER_COLOR = '#FFFFFF';

    public const BORDER_COLOR = '#000000';

    /**
     * Highest image of a block in the layout "one_column": wider than tall images would cover the
     * whole slide. In the other layouts the image takes the whole width of its column, up to the
     * height of the area.
     */
    public const IMAGE_MAX_HEIGHT = 420;

    /** Block "image_text": share of the column for the image, its highest size, gap to the text. */
    public const INLINE_IMAGE_SHARE = 0.38;

    public const INLINE_IMAGE_MAX_HEIGHT = 320;

    public const INLINE_GAP = 28;

    /** An image cut at the bottom of the area smaller than this is left out. */
    public const MIN_IMAGE_HEIGHT = 80;

    public const MIN_SCALE = 0.7;

    public const SCALE_STEP = 0.05;

    /** Margin on the measured widths, like TextFitter (differences between GD and Chromium). */
    private const SAFETY = 0.97;

    private const ELLIPSIS = '…';

    /**
     * @param  array<int, array{path: string, width: int, height: int}>  $images  file id → file
     */
    public static function page(?string $backgroundPath, CustomSlide $slide, array $images): LayoutPage
    {
        return new LayoutPage(DocumentLayout::CustomBlocks, $backgroundPath, [
            'slide' => $slide,
            'images' => $images,
        ]);
    }

    /**
     * The elements for the view, with the images copied next to the page.
     *
     * @param  array<string, mixed>  $data
     * @param  Closure(string): string  $asset
     * @return array{elements: list<array<string, mixed>>}
     */
    public static function viewData(array $data, TextFitter $fitter, Closure $asset): array
    {
        $slide = $data['slide'] ?? null;

        if (! $slide instanceof CustomSlide) {
            return ['elements' => []];
        }

        /** @var array<int, array{path: string, width: int, height: int}> $images */
        $images = is_array($data['images'] ?? null) ? $data['images'] : [];
        $sizes = array_map(fn (array $image): array => [$image['width'], $image['height']], $images);
        $elements = [];

        foreach (self::arrange($slide, $sizes, $fitter)->elements as $element) {
            if ($element['kind'] === 'image') {
                $fileId = $element['file_id'];

                if (! is_int($fileId) || ! isset($images[$fileId])) {
                    continue;
                }

                $element['src'] = $asset($images[$fileId]['path']);
            }

            $elements[] = $element;
        }

        return ['elements' => $elements];
    }

    /**
     * Places the blocks of a slide in the content area.
     *
     * @param  array<int, array{int, int}>  $sizes  file id → [width, height] of the images available
     */
    public static function arrange(CustomSlide $slide, array $sizes, TextFitter $fitter): CustomArrangement
    {
        [$areaLeft, $areaTop, $areaWidth, $areaHeight] = self::AREA;
        $fixed = [];
        $columns = [];

        if ($slide->layout === CustomSlideLayout::ImageText) {
            $imageWidth = (int) round($areaWidth * self::SLIDE_IMAGE_SHARE);
            $size = $slide->imageFileId !== null ? ($sizes[$slide->imageFileId] ?? null) : null;

            if ($size !== null) {
                [$width, $height] = self::contain($size, $imageWidth, $areaHeight);
                $fixed[] = ['kind' => 'image', 'block' => -1, 'file_id' => $slide->imageFileId, 'left' => $areaLeft, 'top' => 0.0, 'width' => $width, 'height' => $height];
            }

            $columns[] = [$areaLeft + $imageWidth + self::COLUMN_GAP, $areaWidth - $imageWidth - self::COLUMN_GAP, array_keys($slide->blocks)];
        } elseif ($slide->layout === CustomSlideLayout::TwoColumns) {
            $width = intdiv($areaWidth - self::COLUMN_GAP, 2);
            $left = [];
            $right = [];

            foreach ($slide->blocks as $index => $block) {
                if (($block['column'] ?? null) === CustomSlideContent::RIGHT) {
                    $right[] = $index;
                } else {
                    $left[] = $index;
                }
            }

            $columns[] = [$areaLeft, $width, $left];
            $columns[] = [$areaLeft + $width + self::COLUMN_GAP, $width, $right];
        } else {
            $columns[] = [$areaLeft, $areaWidth, array_keys($slide->blocks)];
        }

        $imageMaxHeight = $slide->layout === CustomSlideLayout::OneColumn ? self::IMAGE_MAX_HEIGHT : $areaHeight;
        $scale = 1.0;

        while (true) {
            [$elements, $height] = self::columns($slide, $columns, $scale, $sizes, $fitter, $imageMaxHeight);

            if ($height <= $areaHeight) {
                return new CustomArrangement(self::offset([...$fixed, ...$elements], $areaTop), false, $scale);
            }

            if ($scale <= self::MIN_SCALE + 0.001) {
                break;
            }

            $scale = max(self::MIN_SCALE, round($scale - self::SCALE_STEP, 2));
        }

        return new CustomArrangement(self::offset([...$fixed, ...self::clip($elements, $areaHeight, $fitter)], $areaTop), true, $scale);
    }

    /**
     * @param  list<array{int, int, list<int>}>  $columns  [left, width, block indexes]
     * @param  array<int, array{int, int}>  $sizes
     * @return array{list<array<string, mixed>>, float} elements (top relative to the area), height
     */
    private static function columns(CustomSlide $slide, array $columns, float $scale, array $sizes, TextFitter $fitter, int $imageMaxHeight): array
    {
        $elements = [];
        $height = 0.0;

        foreach ($columns as [$left, $width, $indexes]) {
            $top = 0.0;
            $first = true;

            foreach ($indexes as $index) {
                [$blockElements, $blockHeight] = self::block($slide->blocks[$index], $index, $left, $width, $scale, $sizes, $fitter, $imageMaxHeight);

                if ($blockHeight <= 0) {
                    continue;
                }

                if (! $first) {
                    $top += self::BLOCK_GAP * $scale;
                }

                $first = false;

                foreach ($blockElements as $element) {
                    $element['top'] += $top;
                    $elements[] = $element;
                }

                $top += $blockHeight;
            }

            $height = max($height, $top);
        }

        return [$elements, $height];
    }

    /**
     * The elements of one block, from its top.
     *
     * @param  array<string, mixed>  $block
     * @param  array<int, array{int, int}>  $sizes
     * @return array{list<array<string, mixed>>, float}
     */
    private static function block(array $block, int $index, int $left, int $width, float $scale, array $sizes, TextFitter $fitter, int $imageMaxHeight): array
    {
        $type = CustomBlockType::tryFrom(is_string($block['type'] ?? null) ? $block['type'] : '');
        $text = is_string($block['text'] ?? null) ? $block['text'] : '';
        $fileId = is_int($block['file_id'] ?? null) ? $block['file_id'] : null;
        $size = $fileId !== null ? ($sizes[$fileId] ?? null) : null;

        return match ($type) {
            CustomBlockType::Title => self::title($text, $index, $left, $width, $scale, $fitter),
            CustomBlockType::Text => self::text($text, $index, $left, $width, $scale, $fitter),
            CustomBlockType::Table => self::table(is_array($block['rows'] ?? null) ? $block['rows'] : [], $index, $left, $width, $scale, $fitter),
            CustomBlockType::Image => $size !== null ? self::image($fileId, $size, $index, $left, $width, $imageMaxHeight * $scale) : [[], 0.0],
            CustomBlockType::ImageText => self::imageText($fileId, $size, $text, $index, $left, $width, $scale, $fitter),
            null => [[], 0.0],
        };
    }

    /**
     * @return array{list<array<string, mixed>>, float}
     */
    private static function title(string $text, int $index, int $left, int $width, float $scale, TextFitter $fitter): array
    {
        $paragraphs = $text !== '' ? [new CustomParagraph(false, [['text' => $text, 'bold' => true]])] : [];

        return self::paragraphs(new CustomText($paragraphs), $index, $left, $width, self::TITLE_SIZE * $scale, self::TITLE_LINE, self::TITLE_COLOR, DocumentFontWeight::Black, DocumentFontWeight::Black, $fitter);
    }

    /**
     * @return array{list<array<string, mixed>>, float}
     */
    private static function text(string $text, int $index, int $left, int $width, float $scale, TextFitter $fitter): array
    {
        return self::paragraphs(CustomText::parse($text), $index, $left, $width, self::TEXT_SIZE * $scale, self::TEXT_LINE, self::TEXT_COLOR, DocumentFontWeight::Regular, DocumentFontWeight::Bold, $fitter);
    }

    /**
     * @param  array{int, int}|null  $size
     * @return array{list<array<string, mixed>>, float}
     */
    private static function imageText(?int $fileId, ?array $size, string $text, int $index, int $left, int $width, float $scale, TextFitter $fitter): array
    {
        if ($fileId === null || $size === null) {
            return self::text($text, $index, $left, $width, $scale, $fitter);
        }

        [$image, $imageHeight] = self::image($fileId, $size, $index, $left, (int) round($width * self::INLINE_IMAGE_SHARE), self::INLINE_IMAGE_MAX_HEIGHT * $scale);
        $imageWidth = (float) ($image[0]['width'] ?? 0);
        $textLeft = (int) round($left + $imageWidth + self::INLINE_GAP * $scale);
        [$lines, $textHeight] = self::text($text, $index, $textLeft, $left + $width - $textLeft, $scale, $fitter);

        return [[...$image, ...$lines], max($imageHeight, $textHeight)];
    }

    /**
     * An image on the width of the column, never higher than the limit, aligned on the left.
     *
     * @param  array{int, int}  $size
     * @return array{list<array<string, mixed>>, float}
     */
    private static function image(?int $fileId, array $size, int $index, int $left, int $width, float $maxHeight): array
    {
        [$imageWidth, $imageHeight] = self::contain($size, $width, $maxHeight);

        return [[['kind' => 'image', 'block' => $index, 'file_id' => $fileId, 'left' => $left, 'top' => 0.0, 'width' => $imageWidth, 'height' => $imageHeight]], $imageHeight];
    }

    /**
     * @param  array<mixed>  $rows
     * @return array{list<array<string, mixed>>, float}
     */
    private static function table(array $rows, int $index, int $left, int $width, float $scale, TextFitter $fitter): array
    {
        $rows = array_values(array_filter($rows, is_array(...)));

        if ($rows === []) {
            return [[], 0.0];
        }

        $size = self::TABLE_SIZE * $scale;
        $rowHeight = round($size * self::TABLE_ROW, 1);
        $padding = round($size * self::TABLE_PADDING, 1);
        $columns = max(1, ...array_map(count(...), $rows));
        $cellWidth = $width / $columns;
        $elements = [];

        foreach ($rows as $rowIndex => $cells) {
            $header = $rowIndex === 0;
            $top = $rowIndex * $rowHeight;
            $cells = array_values($cells);

            for ($column = 0; $column < $columns; $column++) {
                $cellLeft = round($left + $column * $cellWidth, 1);
                $elements[] = [
                    'kind' => 'rect', 'block' => $index, 'row' => $rowIndex,
                    'left' => $cellLeft, 'top' => $top, 'width' => round($cellWidth, 1), 'height' => $rowHeight,
                    'background' => $header ? self::HEADER_BACKGROUND : null, 'border' => self::BORDER_COLOR,
                ];
                $value = is_string($cells[$column] ?? null) ? $cells[$column] : '';

                if ($value === '') {
                    continue;
                }

                $weight = $header ? DocumentFontWeight::Bold : DocumentFontWeight::Regular;
                $fitted = $fitter->fit($value, $weight, $size, $cellWidth - 2 * $padding);
                $elements[] = [
                    'kind' => 'cell', 'block' => $index, 'row' => $rowIndex,
                    'left' => round($cellLeft + $padding, 1), 'top' => $top, 'width' => round($cellWidth - 2 * $padding, 1), 'height' => $rowHeight,
                    'size' => $fitted->size, 'weight' => $weight->value, 'color' => $header ? self::HEADER_COLOR : self::TEXT_COLOR,
                    'text' => $fitted->text,
                ];
            }
        }

        return [$elements, count($rows) * $rowHeight];
    }

    /**
     * Paragraphs wrapped on the width: one element per line, the bullet as an element of its own.
     *
     * @return array{list<array<string, mixed>>, float}
     */
    private static function paragraphs(CustomText $text, int $index, int $left, int $width, float $size, float $lineFactor, string $color, DocumentFontWeight $normal, DocumentFontWeight $bold, TextFitter $fitter): array
    {
        $lineHeight = round($size * $lineFactor, 1);
        $indent = round($size * self::BULLET_INDENT, 1);
        $elements = [];
        $top = 0.0;
        $previous = false;

        foreach ($text->paragraphs as $paragraph) {
            if ($paragraph->isEmpty()) {
                $top += $size * self::EMPTY_LINE;
                $previous = false;

                continue;
            }

            if ($previous) {
                $top += $size * self::PARAGRAPH_GAP;
            }

            $previous = true;
            $textLeft = $paragraph->bullet ? $left + $indent : $left;
            $textWidth = $paragraph->bullet ? $width - $indent : $width;
            $lines = self::wrap($paragraph->runs, $textWidth, $size, $normal, $bold, $fitter);

            foreach ($lines as $number => $runs) {
                if ($paragraph->bullet && $number === 0) {
                    $elements[] = self::line($index, $left, $top, $indent, $lineHeight, $size, $color, [['text' => self::BULLET, 'weight' => $normal->value]], true);
                }

                $elements[] = self::line($index, $textLeft, $top, $textWidth, $lineHeight, $size, $color, $runs, false);
                $top += $lineHeight;
            }
        }

        return [$elements, round($top, 1)];
    }

    /**
     * @param  list<array{text: string, weight: int}>  $runs
     * @return array<string, mixed>
     */
    private static function line(int $index, float|int $left, float $top, float|int $width, float $height, float $size, string $color, array $runs, bool $marker): array
    {
        return [
            'kind' => 'line', 'block' => $index, 'marker' => $marker,
            'left' => round((float) $left, 1), 'top' => $top, 'width' => round((float) $width, 1), 'height' => $height,
            'size' => round($size, 1), 'color' => $color, 'runs' => $runs,
        ];
    }

    /**
     * Greedy wrapping of the words of a paragraph; a word longer than the line is broken.
     *
     * @param  list<array{text: string, bold: bool}>  $runs
     * @return list<list<array{text: string, weight: int}>>
     */
    private static function wrap(array $runs, float|int $width, float $size, DocumentFontWeight $normal, DocumentFontWeight $bold, TextFitter $fitter): array
    {
        $available = $width * self::SAFETY;
        $space = max(0.0, $fitter->width('a a', $normal, $size) - $fitter->width('aa', $normal, $size));
        $words = self::words($runs, $normal, $bold);
        $lines = [];
        $line = [];
        $lineWidth = 0.0;

        foreach ($words as $word) {
            $pieces = $word;
            $wordWidth = self::piecesWidth($pieces, $size, $fitter);

            if ($line !== [] && $lineWidth + $space + $wordWidth <= $available) {
                $line[] = ['text' => ' ', 'weight' => $line[array_key_last($line)]['weight']];
                array_push($line, ...$pieces);
                $lineWidth += $space + $wordWidth;

                continue;
            }

            if ($line !== []) {
                $lines[] = $line;
                $line = [];
                $lineWidth = 0.0;
            }

            while ($wordWidth > $available && self::length($pieces) > 1) {
                [$head, $pieces] = self::breakWord($pieces, $available, $size, $fitter);
                $lines[] = $head;
                $wordWidth = self::piecesWidth($pieces, $size, $fitter);
            }

            $line = $pieces;
            $lineWidth = $wordWidth;
        }

        if ($line !== []) {
            $lines[] = $line;
        }

        return array_map(self::merge(...), $lines);
    }

    /**
     * The words of a paragraph, each as pieces with their weight (a word can change weight inside).
     *
     * @param  list<array{text: string, bold: bool}>  $runs
     * @return list<list<array{text: string, weight: int}>>
     */
    private static function words(array $runs, DocumentFontWeight $normal, DocumentFontWeight $bold): array
    {
        $words = [];
        $current = [];

        foreach ($runs as $run) {
            $weight = $run['bold'] ? $bold->value : $normal->value;

            foreach (preg_split('/(\s+)/u', $run['text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                if (trim($token) === '') {
                    if ($current !== []) {
                        $words[] = $current;
                        $current = [];
                    }

                    continue;
                }

                $current[] = ['text' => $token, 'weight' => $weight];
            }
        }

        if ($current !== []) {
            $words[] = $current;
        }

        return $words;
    }

    /**
     * The longest head of a word that fits the width (at least one character), and the rest.
     *
     * @param  list<array{text: string, weight: int}>  $pieces
     * @return array{list<array{text: string, weight: int}>, list<array{text: string, weight: int}>}
     */
    private static function breakWord(array $pieces, float $available, float $size, TextFitter $fitter): array
    {
        $characters = [];

        foreach ($pieces as $piece) {
            foreach (mb_str_split($piece['text']) as $character) {
                $characters[] = ['text' => $character, 'weight' => $piece['weight']];
            }
        }

        $count = count($characters) - 1;

        while ($count > 1 && self::piecesWidth(array_slice($characters, 0, $count), $size, $fitter) > $available) {
            $count--;
        }

        return [self::merge(array_slice($characters, 0, $count)), self::merge(array_slice($characters, $count))];
    }

    /**
     * @param  list<array{text: string, weight: int}>  $pieces
     */
    private static function piecesWidth(array $pieces, float $size, TextFitter $fitter): float
    {
        $width = 0.0;

        foreach (self::merge($pieces) as $piece) {
            $width += $fitter->width($piece['text'], DocumentFontWeight::from($piece['weight']), $size);
        }

        return $width;
    }

    /**
     * @param  list<array{text: string, weight: int}>  $pieces
     */
    private static function length(array $pieces): int
    {
        return array_sum(array_map(fn (array $piece): int => mb_strlen($piece['text']), $pieces));
    }

    /**
     * Adjacent pieces of the same weight joined.
     *
     * @param  list<array{text: string, weight: int}>  $pieces
     * @return list<array{text: string, weight: int}>
     */
    private static function merge(array $pieces): array
    {
        $merged = [];

        foreach ($pieces as $piece) {
            $last = array_key_last($merged);

            if ($last !== null && $merged[$last]['weight'] === $piece['weight']) {
                $merged[$last]['text'] .= $piece['text'];
            } else {
                $merged[] = $piece;
            }
        }

        return $merged;
    }

    /**
     * What fits in the height of the area: an image is reduced to the space left (or left out), the
     * last line kept of a cut text ends with "…", the rest is left out.
     *
     * @param  list<array<string, mixed>>  $elements
     * @return list<array<string, mixed>>
     */
    private static function clip(array $elements, int $height, TextFitter $fitter): array
    {
        $kept = [];
        $cut = [];

        foreach ($elements as $element) {
            $top = (float) $element['top'];
            $bottom = $top + (float) $element['height'];

            if ($bottom <= $height + 0.5) {
                $kept[] = $element;

                continue;
            }

            if ($element['kind'] === 'image' && $height - $top >= self::MIN_IMAGE_HEIGHT) {
                $ratio = ($height - $top) / (float) $element['height'];
                $element['width'] = round((float) $element['width'] * $ratio, 1);
                $element['height'] = round($height - $top, 1);
                $kept[] = $element;

                continue;
            }

            if ($element['kind'] === 'line' && $element['marker'] !== true) {
                $cut[(int) $element['block']] = true;
            }
        }

        foreach (array_keys($cut) as $block) {
            for ($index = count($kept) - 1; $index >= 0; $index--) {
                $element = $kept[$index];

                if ($element['kind'] === 'line' && $element['block'] === $block && $element['marker'] !== true) {
                    /** @var list<array{text: string, weight: int}> $runs */
                    $runs = $element['runs'];
                    $kept[$index]['runs'] = self::ellipsis($runs, (float) $element['width'], (float) $element['size'], $fitter);
                    break;
                }
            }
        }

        return $kept;
    }

    /**
     * A line ended with "…", shortened until it fits.
     *
     * @param  list<array{text: string, weight: int}>  $runs
     * @return list<array{text: string, weight: int}>
     */
    private static function ellipsis(array $runs, float $width, float $size, TextFitter $fitter): array
    {
        $available = $width * self::SAFETY;
        $weight = $runs !== [] ? $runs[array_key_last($runs)]['weight'] : DocumentFontWeight::Regular->value;

        while (true) {
            $candidate = [...$runs, ['text' => self::ELLIPSIS, 'weight' => $weight]];

            if ($runs === [] || self::piecesWidth($candidate, $size, $fitter) <= $available) {
                return self::merge($candidate);
            }

            $last = array_key_last($runs);
            $text = rtrim(mb_substr($runs[$last]['text'], 0, -1));

            if ($text === '') {
                array_pop($runs);
            } else {
                $runs[$last]['text'] = $text;
            }
        }
    }

    /**
     * Width and height of an image fitted in a box, proportions kept.
     *
     * @param  array{int, int}  $size
     * @return array{float, float}
     */
    private static function contain(array $size, float|int $width, float|int $height): array
    {
        [$imageWidth, $imageHeight] = [max(1, $size[0]), max(1, $size[1])];
        $ratio = min($width / $imageWidth, $height / $imageHeight);

        return [round($imageWidth * $ratio, 1), round($imageHeight * $ratio, 1)];
    }

    /**
     * Tops relative to the area made absolute.
     *
     * @param  list<array<string, mixed>>  $elements
     * @return list<array<string, mixed>>
     */
    private static function offset(array $elements, int $top): array
    {
        return array_map(function (array $element) use ($top): array {
            $element['top'] = round((float) $element['top'] + $top, 1);

            return $element;
        }, $elements);
    }
}
