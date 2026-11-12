<?php

namespace App\Domain\Documents\Layouts;

use App\Enums\DocumentLayout;

/**
 * Geometry and colours of an economic recap. The network recap (layout-dinamici §4.8, D-314) and
 * the club recap (§4.9, D-415) share the same grid, columns and texts; the club one starts lower on
 * its background, has taller rows and a header in the colour of the club, whose name heads the first
 * column. The title of a variant ("Proposta A", D-396) sits right-aligned above the table.
 */
final readonly class RecapStyle
{
    public const TITLE_HEIGHT = 50;

    public const TITLE_SIZE = 32;

    public const TITLE_GAP = 24;

    public function __construct(
        public DocumentLayout $layout,
        public int $headerTop,
        public int $headerHeight,
        public string $headerBackground,
        public int $rowsTop,
        public int $rowHeight,
        public int $totalsHeight,
        public int $rowsPerPage,
        public ?string $firstColumnTitle = null,
        public int $nameLeft = NetworkRecapLayout::NAME_LEFT,
    ) {}

    /** The network recap of the original (page 14 of the network example, D-314). */
    public static function network(): self
    {
        return new self(
            layout: DocumentLayout::NetworkRecap,
            headerTop: NetworkRecapLayout::HEADER_TOP,
            headerHeight: NetworkRecapLayout::HEADER_HEIGHT,
            headerBackground: NetworkRecapLayout::HEADER_BACKGROUND,
            rowsTop: NetworkRecapLayout::ROWS_TOP,
            rowHeight: NetworkRecapLayout::ROW_HEIGHT,
            totalsHeight: NetworkRecapLayout::TOTALS_HEIGHT,
            rowsPerPage: NetworkRecapLayout::ROWS_PER_PAGE,
        );
    }

    /**
     * The club recap (page 29 of the Udinese example, layout-dinamici §4.9, D-415): header at top 453,
     * 105 high, in the primary colour of the club (the red of the network without it); rows from top
     * 559, 60 high; totals 90 high; the name of the club heads the first column.
     */
    public static function club(?string $color, string $clubName): self
    {
        return new self(
            layout: DocumentLayout::ClubRecap,
            headerTop: 453,
            headerHeight: 105,
            headerBackground: $color ?? NetworkRecapLayout::HEADER_BACKGROUND,
            rowsTop: 559,
            rowHeight: 60,
            totalsHeight: 90,
            rowsPerPage: 5,
            firstColumnTitle: mb_strtoupper($clubName),
            nameLeft: NetworkRecapLayout::COLUMNS['club'][0] + NetworkRecapLayout::CELL_PADDING,
        );
    }

    /** Top of the title of a variant, right above the header. */
    public function titleTop(): int
    {
        return $this->headerTop - self::TITLE_GAP - self::TITLE_HEIGHT;
    }
}
