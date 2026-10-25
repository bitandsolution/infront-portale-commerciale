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
 * Network economic recap (layout-dinamici §4.8): measures in pixels on the 1920×1080 canvas, taken
 * from page 14 of the network example, with the adjustments approved by D-254 (list price on each
 * row, list total · discount · final total on separate lines, readable white headers, Interstate
 * instead of Calibri, D-249). All the measures of the layout live here, not in the Blade view
 * (layout-dinamici §6.1).
 */
final class NetworkRecapLayout
{
    /**
     * Columns [left, right]: club, format, matches, minutes per match, spots per match, price.
     *
     * @var array<string, array{int, int}>
     */
    public const COLUMNS = [
        'club' => [56, 510],
        'format' => [510, 789],
        'matches' => [789, 994],
        'minutes' => [994, 1198],
        'spots' => [1198, 1402],
        'price' => [1402, 1855],
    ];

    public const HEADER_TOP = 226;

    public const HEADER_HEIGHT = 105;

    public const HEADER_BACKGROUND = '#CC0033';

    public const HEADER_COLOR = '#FFFFFF';

    public const HEADER_SIZE = 20;

    public const ROWS_TOP = 332;

    public const ROW_HEIGHT = 45;

    public const LOGO_LEFT = 68;

    public const LOGO_SIZE = 37;

    /**
     * The club name starts after the logo, with a gap of 10 px.
     */
    public const NAME_LEFT = 115;

    public const TEXT_SIZE = 20;

    public const HOSPITALITY_SIZE = 16;

    public const TEXT_COLOR = '#333333';

    /**
     * Horizontal padding inside the cells, so text never touches the borders.
     */
    public const CELL_PADDING = 12;

    public const TOTALS_HEIGHT = 90;

    /**
     * "Totale / stagione" is right-aligned at this x.
     */
    public const TOTAL_LABEL_RIGHT = 733;

    public const TOTALS_SIZE = 37.3;

    /**
     * Final total alone (no discount), and the three lines with a discount: list total struck
     * through, discount, final total, which together fit in the 90 px of the totals row.
     */
    public const FINAL_SIZE = 46.7;

    public const FINAL_SIZE_WITH_DISCOUNT = 36;

    public const LIST_DISCOUNT_SIZE = 18;

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
    public const RELEGATION_NOTE_BOX = [56, 941, 1790, 30];

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
     * Everything the Blade view needs, already measured and formatted: the view only places boxes
     * at the given coordinates. Logos are referenced through $asset, which copies a file into the
     * folder of the job and returns its relative name (D-312).
     *
     * @param  array<string, mixed>  $data  data of one page, from pages()
     * @param  Closure(string): string  $asset
     * @return array<string, mixed>
     */
    public static function viewData(array $data, TextFitter $fitter, Closure $asset): array
    {
        /** @var list<RecapRow> $rows */
        $rows = $data['rows'] ?? [];
        $totals = ($data['totals'] ?? null) instanceof RecapTotals ? $data['totals'] : null;
        $relegationNote = is_string($data['relegationNote'] ?? null) ? $data['relegationNote'] : null;

        $view = [
            'textColor' => self::TEXT_COLOR,
            'header' => [
                'left' => self::COLUMNS['club'][0],
                'top' => self::HEADER_TOP,
                'width' => self::COLUMNS['price'][1] - self::COLUMNS['club'][0],
                'height' => self::HEADER_HEIGHT,
                'background' => self::HEADER_BACKGROUND,
                'color' => self::HEADER_COLOR,
                'size' => self::HEADER_SIZE,
                'cells' => array_map(
                    fn (string $column): array => self::cell(
                        $column,
                        new FittedText(Text::get('documents.network_recap.columns.'.$column), self::HEADER_SIZE),
                        DocumentFontWeight::Black,
                        $column === 'club' ? 'left' : 'center',
                    ),
                    array_keys(self::COLUMNS),
                ),
            ],
            'rows' => [],
            'totals' => null,
            'vatNote' => null,
            'relegationNote' => null,
        ];

        $top = self::ROWS_TOP;

        foreach ($rows as $row) {
            $view['rows'][] = self::row($row, $top, $fitter, $asset);
            $top += self::ROW_HEIGHT;
        }

        if ($totals !== null) {
            $view['totals'] = self::totals($totals, $top);
            $top += self::TOTALS_HEIGHT;

            $view['vatNote'] = [
                'text' => Text::get('documents.network_recap.vat_note'),
                'left' => self::COLUMNS['club'][0],
                'top' => $top + self::VAT_NOTE_GAP,
                'size' => self::VAT_NOTE_SIZE,
            ];
        }

        if ($relegationNote !== null) {
            [$left, $noteTop, $width, $height] = self::RELEGATION_NOTE_BOX;
            $view['relegationNote'] = [
                'left' => $left,
                'top' => $noteTop,
                'width' => $width,
                'height' => $height,
                'text' => $fitter->fit($relegationNote, DocumentFontWeight::Bold, self::RELEGATION_NOTE_SIZE, $width),
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
     * @param  Closure(string): string  $asset
     * @return array<string, mixed>
     */
    private static function row(RecapRow $row, int $top, TextFitter $fitter, Closure $asset): array
    {
        $size = $row->isHospitality ? self::HOSPITALITY_SIZE : self::TEXT_SIZE;
        $nameWidth = self::COLUMNS['club'][1] - self::NAME_LEFT - self::CELL_PADDING;
        // A hospitality row has no LED: its LED cells stay empty instead of showing "—".
        $dash = $row->isHospitality ? '' : Text::get('documents.network_recap.none');

        return [
            'top' => $top,
            'height' => self::ROW_HEIGHT,
            'hospitality' => $row->isHospitality,
            'logo' => $row->logoPath !== null && ! $row->isHospitality ? [
                'src' => $asset($row->logoPath),
                'left' => self::LOGO_LEFT,
                'top' => $top + intdiv(self::ROW_HEIGHT - self::LOGO_SIZE, 2),
                'size' => self::LOGO_SIZE,
            ] : null,
            'cells' => [
                [
                    'left' => self::NAME_LEFT,
                    'width' => $nameWidth,
                    'align' => 'left',
                    'weight' => DocumentFontWeight::Bold->value,
                    'text' => $fitter->fit($row->label, DocumentFontWeight::Bold, $size, $nameWidth),
                ],
                self::cell('format', $fitter->fit($row->format, DocumentFontWeight::Bold, $size, self::innerWidth('format')), DocumentFontWeight::Bold),
                self::cell('matches', new FittedText($row->matches === null ? $dash : DocumentNumbers::integer($row->matches), $size), DocumentFontWeight::Bold),
                self::cell('minutes', new FittedText($row->secondsPerMatch === null ? $dash : DocumentNumbers::minutesPerMatch($row->secondsPerMatch), $size), DocumentFontWeight::Bold),
                self::cell('spots', new FittedText($row->spotsPerMatch === null ? $dash : DocumentNumbers::integer($row->spotsPerMatch), $size), DocumentFontWeight::Bold),
                self::cell('price', new FittedText(DocumentNumbers::amount($row->listPrice), $size), DocumentFontWeight::Bold),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function totals(RecapTotals $totals, int $top): array
    {
        $lines = $totals->hasDiscount()
            ? [
                ['text' => DocumentNumbers::amount($totals->listTotal), 'size' => self::LIST_DISCOUNT_SIZE, 'weight' => DocumentFontWeight::Bold->value, 'strike' => true],
                ['text' => (string) $totals->discountLabel, 'size' => self::LIST_DISCOUNT_SIZE, 'weight' => DocumentFontWeight::Bold->value, 'strike' => false],
                ['text' => DocumentNumbers::amount($totals->finalTotal), 'size' => self::FINAL_SIZE_WITH_DISCOUNT, 'weight' => DocumentFontWeight::Black->value, 'strike' => false],
            ]
            : [
                ['text' => DocumentNumbers::amount($totals->finalTotal), 'size' => self::FINAL_SIZE, 'weight' => DocumentFontWeight::Black->value, 'strike' => false],
            ];

        return [
            'top' => $top,
            'height' => self::TOTALS_HEIGHT,
            'left' => self::COLUMNS['club'][0],
            'width' => self::COLUMNS['price'][1] - self::COLUMNS['club'][0],
            'label' => [
                'text' => Text::get('documents.network_recap.total'),
                'right' => 1920 - self::TOTAL_LABEL_RIGHT,
                'size' => self::TOTALS_SIZE,
            ],
            'cells' => [
                self::cell('matches', new FittedText(DocumentNumbers::integer($totals->matches), self::TOTALS_SIZE), DocumentFontWeight::Bold),
                self::cell('minutes', new FittedText(DocumentNumbers::totalMinutes($totals->seconds), self::TOTALS_SIZE), DocumentFontWeight::Bold),
                self::cell('spots', new FittedText(DocumentNumbers::integer($totals->spots), self::TOTALS_SIZE), DocumentFontWeight::Bold),
            ],
            'price' => [
                'left' => self::COLUMNS['price'][0] + self::CELL_PADDING,
                'width' => self::innerWidth('price'),
                'lines' => $lines,
            ],
        ];
    }

    /**
     * A text box inside a column, with the padding of the cells.
     *
     * @return array<string, mixed>
     */
    private static function cell(string $column, FittedText $text, DocumentFontWeight $weight, string $align = 'center'): array
    {
        return [
            'left' => self::COLUMNS[$column][0] + self::CELL_PADDING,
            'width' => self::innerWidth($column),
            'align' => $align,
            'weight' => $weight->value,
            'text' => $text,
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
