<?php

namespace App\Domain\Documents\Layouts;

use App\Domain\Documents\Data\FittedText;
use App\Domain\Documents\Data\LayoutPage;
use App\Domain\Documents\Data\NetworkRecap;
use App\Domain\Documents\Data\RecapRow;
use App\Domain\Documents\Data\RecapTotals;
use App\Domain\Documents\Services\TextFitter;
use App\Enums\DocumentFontWeight;
use App\Enums\DocumentLayout;
use App\Support\Documents\DocumentNumbers;
use App\Support\Text;
use Closure;

/**
 * Network economic recap: the graphic of the original document (page 14 of the network example,
 * layout-dinamici §4.8), as approved by D-314:
 *
 * - table with a complete grid of thin black borders on a transparent background;
 * - red header with the original column titles, written in white (defect 1 of §2.4 corrected);
 * - club names in capitals with the logo on the left;
 * - "Totale / stagione" without border, totals of matches, minutes and spots in bordered cells;
 * - PROPOSTA ECONOMICA as one merged cell over all the rows and the totals: the final total or,
 *   with a discount, the list total struck through above the final total, never overlapped
 *   (defect 2 of §2.4 corrected); no price per club;
 * - hospitality: a grid row under its club, LED cells empty, amount only in the total.
 *
 * Interstate instead of Calibri (D-249). All the measures live here, not in the Blade view
 * (layout-dinamici §6.1): the view only draws the boxes and the texts it receives.
 */
final class NetworkRecapLayout
{
    /**
     * Columns [left, right], from the original.
     *
     * @var array<string, array{int, int}>
     */
    public const COLUMNS = [
        'club' => [56, 510],
        'format' => [510, 790],
        'matches' => [790, 994],
        'minutes' => [994, 1198],
        'spots' => [1198, 1402],
        'price' => [1402, 1855],
    ];

    public const HEADER_TOP = 226;

    public const HEADER_HEIGHT = 106;

    public const HEADER_BACKGROUND = '#CC0033';

    public const HEADER_COLOR = '#FFFFFF';

    public const HEADER_SIZE = 20;

    public const BORDER_COLOR = '#000000';

    public const TEXT_COLOR = '#333333';

    public const ROWS_TOP = 332;

    public const ROW_HEIGHT = 45;

    public const LOGO_LEFT = 68;

    public const LOGO_SIZE = 37;

    /**
     * The club name starts at x = 136, as in the original.
     */
    public const NAME_LEFT = 136;

    public const TEXT_SIZE = 20;

    /**
     * Horizontal padding inside the cells, so text never touches the borders.
     */
    public const CELL_PADDING = 12;

    public const TOTALS_HEIGHT = 91;

    /**
     * "Totale / stagione" is right-aligned at this x.
     */
    public const TOTAL_LABEL_RIGHT = 733;

    public const TOTALS_SIZE = 37.3;

    /**
     * Final total in the merged cell; with a discount the list total, struck through, sits above.
     */
    public const FINAL_SIZE = 46.7;

    public const LIST_TOTAL_SIZE = 30;

    public const PRICE_LINE_GAP = 10;

    /**
     * Note "Importi IVA esclusa" (RB-08 M03, layout-dinamici §4.10), under the table.
     */
    public const VAT_NOTE_GAP = 12;

    public const VAT_NOTE_SIZE = 16;

    /**
     * Note on the relegated teams [left, top, width, height].
     *
     * @var array{int, int, int, int}
     */
    public const RELEGATION_NOTE_BOX = [56, 935, 1790, 30];

    public const RELEGATION_NOTE_SIZE = 21.3;

    /**
     * Capacity of one slide: club rows plus hospitality rows. Beyond it the table continues on the
     * next slide with the same background; the totals appear only on the last one.
     */
    public const ROWS_PER_PAGE = 10;

    /**
     * The pages of the recap: each with at most ROWS_PER_PAGE rows, the totals and the notes only on
     * the last. A hospitality row stays on the page of its club.
     *
     * @return list<LayoutPage>
     */
    public static function pages(NetworkRecap $recap, string $backgroundPath): array
    {
        $chunks = self::chunks($recap->rows);
        $pages = [];

        foreach ($chunks as $index => $rows) {
            $last = $index === count($chunks) - 1;

            $pages[] = new LayoutPage(DocumentLayout::NetworkRecap, $backgroundPath, [
                'rows' => $rows,
                'totals' => $last ? $recap->totals : null,
                'relegationNote' => $last ? $recap->relegationNote : null,
            ]);
        }

        return $pages;
    }

    /**
     * Everything the Blade view needs, already measured and formatted: bordered boxes, texts and
     * logos at their coordinates. Logos are referenced through $asset, which copies a file into the
     * folder of the job and returns its relative name (D-312).
     *
     * @param  array<string, mixed>  $data  data of one page, from pages()
     * @param  Closure(string): string  $asset
     * @return array{boxes: list<array<string, mixed>>, texts: list<array<string, mixed>>, logos: list<array<string, mixed>>}
     */
    public static function viewData(array $data, TextFitter $fitter, Closure $asset): array
    {
        /** @var list<RecapRow> $rows */
        $rows = $data['rows'] ?? [];
        $totals = ($data['totals'] ?? null) instanceof RecapTotals ? $data['totals'] : null;
        $relegationNote = is_string($data['relegationNote'] ?? null) ? $data['relegationNote'] : null;

        $view = ['boxes' => [], 'texts' => [], 'logos' => []];

        // Header: red cells with black borders and the original titles in white.
        foreach (self::COLUMNS as $column => [$left, $right]) {
            $view['boxes'][] = self::box($left, self::HEADER_TOP, $right - $left, self::HEADER_HEIGHT, self::HEADER_BACKGROUND);
            $view['texts'][] = self::text(
                $column,
                self::HEADER_TOP,
                self::HEADER_HEIGHT,
                $fitter->fit(Text::get('documents.network_recap.columns.'.$column), DocumentFontWeight::Bold, self::HEADER_SIZE, self::innerWidth($column)),
                DocumentFontWeight::Bold,
                self::HEADER_COLOR,
            );
        }

        $top = self::ROWS_TOP;

        foreach ($rows as $row) {
            self::row($view, $row, $top, $fitter, $asset);
            $top += self::ROW_HEIGHT;
        }

        $priceBottom = $top;

        if ($totals !== null) {
            self::totals($view, $totals, $top);
            $top += self::TOTALS_HEIGHT;
            $priceBottom = $top;

            $view['texts'][] = [
                'left' => self::COLUMNS['club'][0],
                'top' => $top + self::VAT_NOTE_GAP,
                'width' => self::COLUMNS['price'][1] - self::COLUMNS['club'][0],
                'height' => self::VAT_NOTE_SIZE + 4,
                'align' => 'left',
                'weight' => DocumentFontWeight::Regular->value,
                'size' => self::VAT_NOTE_SIZE,
                'color' => self::TEXT_COLOR,
                'text' => Text::get('documents.network_recap.vat_note'),
                'strike' => false,
            ];
        }

        // PROPOSTA ECONOMICA: one merged cell over the rows (and the totals, on the last page).
        [$priceLeft, $priceRight] = self::COLUMNS['price'];
        $view['boxes'][] = self::box($priceLeft, self::ROWS_TOP, $priceRight - $priceLeft, $priceBottom - self::ROWS_TOP);

        if ($totals !== null) {
            self::price($view, $totals, self::ROWS_TOP, $priceBottom);
        }

        if ($relegationNote !== null) {
            [$left, $noteTop, $width, $height] = self::RELEGATION_NOTE_BOX;
            $fitted = $fitter->fit($relegationNote, DocumentFontWeight::Bold, self::RELEGATION_NOTE_SIZE, $width);
            $view['texts'][] = [
                'left' => $left,
                'top' => $noteTop,
                'width' => $width,
                'height' => $height,
                'align' => 'left',
                'weight' => DocumentFontWeight::Bold->value,
                'size' => $fitted->size,
                'color' => self::TEXT_COLOR,
                'text' => $fitted->text,
                'strike' => false,
            ];
        }

        return $view;
    }

    /**
     * Width of a column in pixels.
     */
    public static function width(string $column): int
    {
        [$left, $right] = self::COLUMNS[$column];

        return $right - $left;
    }

    /**
     * A club row or a hospitality row: bordered cells from "club" to "spots" (the price column is the
     * merged cell), name in capitals, logo for the clubs.
     *
     * @param  array{boxes: list<array<string, mixed>>, texts: list<array<string, mixed>>, logos: list<array<string, mixed>>}  $view
     * @param  Closure(string): string  $asset
     */
    private static function row(array &$view, RecapRow $row, int $top, TextFitter $fitter, Closure $asset): void
    {
        foreach (['club', 'format', 'matches', 'minutes', 'spots'] as $column) {
            $view['boxes'][] = self::box(self::COLUMNS[$column][0], $top, self::width($column), self::ROW_HEIGHT);
        }

        if ($row->logoPath !== null && ! $row->isHospitality) {
            $view['logos'][] = [
                'src' => $asset($row->logoPath),
                'left' => self::LOGO_LEFT,
                'top' => $top + intdiv(self::ROW_HEIGHT - self::LOGO_SIZE, 2),
                'size' => self::LOGO_SIZE,
            ];
        }

        $nameWidth = self::COLUMNS['club'][1] - self::NAME_LEFT - self::CELL_PADDING;
        $name = $fitter->fit(mb_strtoupper($row->label), DocumentFontWeight::Bold, self::TEXT_SIZE, $nameWidth);
        $view['texts'][] = [
            'left' => self::NAME_LEFT,
            'top' => $top,
            'width' => $nameWidth,
            'height' => self::ROW_HEIGHT,
            'align' => 'left',
            'weight' => DocumentFontWeight::Bold->value,
            'size' => $name->size,
            'color' => self::TEXT_COLOR,
            'text' => $name->text,
            'strike' => false,
        ];

        // A hospitality row has no LED: its LED cells stay empty instead of showing "—".
        $dash = $row->isHospitality ? '' : Text::get('documents.network_recap.none');
        $values = [
            'format' => $row->isHospitality ? '' : $row->format,
            'matches' => $row->matches === null ? $dash : DocumentNumbers::integer($row->matches),
            'minutes' => $row->secondsPerMatch === null ? $dash : DocumentNumbers::minutesPerMatch($row->secondsPerMatch),
            'spots' => $row->spotsPerMatch === null ? $dash : DocumentNumbers::integer($row->spotsPerMatch),
        ];

        foreach ($values as $column => $value) {
            if ($value === '') {
                continue;
            }

            $view['texts'][] = self::text(
                $column,
                $top,
                self::ROW_HEIGHT,
                $fitter->fit($value, DocumentFontWeight::Bold, self::TEXT_SIZE, self::innerWidth($column)),
                DocumentFontWeight::Bold,
            );
        }
    }

    /**
     * "Totale / stagione" without border and the bordered cells of the totals.
     *
     * @param  array{boxes: list<array<string, mixed>>, texts: list<array<string, mixed>>, logos: list<array<string, mixed>>}  $view
     */
    private static function totals(array &$view, RecapTotals $totals, int $top): void
    {
        $label = Text::get('documents.network_recap.total');
        $view['texts'][] = [
            'left' => self::COLUMNS['club'][0],
            'top' => $top,
            'width' => self::TOTAL_LABEL_RIGHT - self::COLUMNS['club'][0],
            'height' => self::TOTALS_HEIGHT,
            'align' => 'right',
            'weight' => DocumentFontWeight::Bold->value,
            'size' => self::TOTALS_SIZE,
            'color' => self::TEXT_COLOR,
            'text' => $label,
            'strike' => false,
        ];

        $values = [
            'matches' => DocumentNumbers::integer($totals->matches),
            'minutes' => DocumentNumbers::totalMinutes($totals->seconds),
            'spots' => DocumentNumbers::integer($totals->spots),
        ];

        foreach ($values as $column => $value) {
            $view['boxes'][] = self::box(self::COLUMNS[$column][0], $top, self::width($column), self::TOTALS_HEIGHT);
            $view['texts'][] = self::text($column, $top, self::TOTALS_HEIGHT, new FittedText($value, self::TOTALS_SIZE), DocumentFontWeight::Bold);
        }
    }

    /**
     * Content of the merged price cell, centred vertically: the final total, or the list total struck
     * through above the final total (D-314: no discount line, no overlap).
     *
     * @param  array{boxes: list<array<string, mixed>>, texts: list<array<string, mixed>>, logos: list<array<string, mixed>>}  $view
     */
    private static function price(array &$view, RecapTotals $totals, int $top, int $bottom): void
    {
        $lines = $totals->hasDiscount()
            ? [
                [DocumentNumbers::amount($totals->listTotal), self::LIST_TOTAL_SIZE, DocumentFontWeight::Bold, true],
                [DocumentNumbers::amount($totals->finalTotal), self::FINAL_SIZE, DocumentFontWeight::Black, false],
            ]
            : [
                [DocumentNumbers::amount($totals->finalTotal), self::FINAL_SIZE, DocumentFontWeight::Black, false],
            ];

        $height = array_sum(array_column($lines, 1)) + self::PRICE_LINE_GAP * (count($lines) - 1);
        $y = $top + ($bottom - $top - $height) / 2;

        foreach ($lines as [$text, $size, $weight, $strike]) {
            $view['texts'][] = [
                'left' => self::COLUMNS['price'][0] + self::CELL_PADDING,
                'top' => round($y, 1),
                'width' => self::innerWidth('price'),
                'height' => $size,
                'align' => 'center',
                'weight' => $weight->value,
                'size' => $size,
                'color' => self::TEXT_COLOR,
                'text' => $text,
                'strike' => $strike,
            ];
            $y += $size + self::PRICE_LINE_GAP;
        }
    }

    /**
     * A bordered box. Adjacent boxes overlap by one pixel, so each border is drawn once.
     *
     * @return array<string, mixed>
     */
    private static function box(int $left, int $top, int $width, int $height, ?string $background = null): array
    {
        return [
            'left' => $left,
            'top' => $top,
            'width' => $width + 1,
            'height' => $height + 1,
            'background' => $background,
            'border' => self::BORDER_COLOR,
        ];
    }

    /**
     * A text centred in a column.
     *
     * @return array<string, mixed>
     */
    private static function text(string $column, float|int $top, float|int $height, FittedText $text, DocumentFontWeight $weight, string $color = self::TEXT_COLOR): array
    {
        return [
            'left' => self::COLUMNS[$column][0] + self::CELL_PADDING,
            'top' => $top,
            'width' => self::innerWidth($column),
            'height' => $height,
            'align' => 'center',
            'weight' => $weight->value,
            'size' => $text->size,
            'color' => $color,
            'text' => $text->text,
            'strike' => false,
        ];
    }

    private static function innerWidth(string $column): int
    {
        return self::width($column) - 2 * self::CELL_PADDING;
    }

    /**
     * Groups each club with the hospitality rows that follow it, then fills the pages with whole
     * groups.
     *
     * @param  list<RecapRow>  $rows
     * @return list<list<RecapRow>>
     */
    private static function chunks(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            if ($row->isHospitality && $groups !== []) {
                $groups[count($groups) - 1][] = $row;
            } else {
                $groups[] = [$row];
            }
        }

        $chunks = [[]];

        foreach ($groups as $group) {
            $current = count($chunks) - 1;

            if ($chunks[$current] !== [] && count($chunks[$current]) + count($group) > self::ROWS_PER_PAGE) {
                $chunks[] = [];
                $current++;
            }

            $chunks[$current] = [...$chunks[$current], ...$group];
        }

        return $chunks;
    }
}
