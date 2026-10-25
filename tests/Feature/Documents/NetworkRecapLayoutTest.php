<?php

use App\Domain\Documents\Data\RecapRow;
use App\Domain\Documents\Data\RecapTotals;
use App\Domain\Documents\Layouts\NetworkRecapLayout;
use App\Domain\Documents\Services\TextFitter;
use App\Enums\DocumentLayout;
use App\Support\Documents\SampleDeck;

/*
| Network recap with the graphic of the original document (layout-dinamici §4.8, D-314): at most 10
| rows per slide with continuation, totals only on the last slide, grid of black borders, original
| column titles in white, names in capitals, PROPOSTA ECONOMICA as one merged cell with the list
| total struck through above the final total. Sample data from SampleDeck (D-309).
*/

/**
 * View data of one page of the recap, with the logos referenced by their file name.
 *
 * @param  array<string, mixed>  $data
 * @return array{boxes: list<array<string, mixed>>, texts: list<array<string, mixed>>, logos: list<array<string, mixed>>}
 */
function recapLayoutView(array $data): array
{
    return NetworkRecapLayout::viewData($data, app(TextFitter::class), fn (string $path): string => basename($path));
}

/**
 * The texts written in a column (from its left edge plus the padding of the cells).
 *
 * @param  array{texts: list<array<string, mixed>>}  $view
 * @return list<array<string, mixed>>
 */
function recapLayoutColumnTexts(array $view, string $column): array
{
    $left = NetworkRecapLayout::COLUMNS[$column][0] + NetworkRecapLayout::CELL_PADDING;

    return array_values(array_filter($view['texts'], fn (array $text): bool => $text['left'] === $left));
}

beforeEach(function () {
    if (! is_file('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf') || ! function_exists('imagettfbbox')) {
        $this->markTestSkipped('fonts-dejavu-core or GD with FreeType is not installed');
    }

    config(['documents.fonts_path' => null]);
});

it('layout §4.8: continues the table on a new slide beyond 10 rows, with the totals only on the last', function () {
    $recap = SampleDeck::recap(12, SampleDeck::DISCOUNT_PERCENT);
    $pages = NetworkRecapLayout::pages($recap, '/sfondo.jpg');

    expect(count($recap->rows))->toBe(16)
        ->and($pages)->toHaveCount(2)
        ->and($pages[0]->layout)->toBe(DocumentLayout::NetworkRecap)
        ->and($pages[0]->backgroundPath)->toBe('/sfondo.jpg')
        ->and(count($pages[0]->data['rows']))->toBeLessThanOrEqual(NetworkRecapLayout::ROWS_PER_PAGE)
        ->and(count($pages[1]->data['rows']))->toBeLessThanOrEqual(NetworkRecapLayout::ROWS_PER_PAGE)
        ->and(count($pages[0]->data['rows']) + count($pages[1]->data['rows']))->toBe(16)
        ->and($pages[0]->data['totals'])->toBeNull()
        ->and($pages[0]->data['relegationNote'])->toBeNull()
        ->and($pages[1]->data['totals'])->toBeInstanceOf(RecapTotals::class)
        ->and($pages[1]->data['relegationNote'])->toBeString();

    // On the first slide the merged price cell is drawn but empty.
    expect(recapLayoutColumnTexts(recapLayoutView($pages[0]->data), 'price'))->toHaveCount(1); // only the header title
});

it('layout §4.8: keeps a hospitality row on the slide of its club', function () {
    foreach (NetworkRecapLayout::pages(SampleDeck::recap(12), '/sfondo.jpg') as $page) {
        /** @var list<RecapRow> $rows */
        $rows = $page->data['rows'];

        expect($rows[0]->isHospitality)->toBeFalse();
    }
});

it('D-314: writes the list total struck through above the final total in the merged cell, without a discount line', function () {
    $pages = NetworkRecapLayout::pages(SampleDeck::recap(3, SampleDeck::DISCOUNT_PERCENT), '/sfondo.jpg');
    $price = array_slice(recapLayoutColumnTexts(recapLayoutView($pages[0]->data), 'price'), 1);

    expect($price)->toHaveCount(2)
        ->and($price[0])->toMatchArray(['text' => '126.000,00 €', 'strike' => true, 'size' => NetworkRecapLayout::LIST_TOTAL_SIZE])
        ->and($price[1])->toMatchArray(['text' => '107.100,00 €', 'strike' => false, 'weight' => 900, 'size' => NetworkRecapLayout::FINAL_SIZE])
        ->and($price[0]['top'] + $price[0]['height'])->toBeLessThan($price[1]['top']);
});

it('D-314: writes only the final total, in Black, when there is no discount', function () {
    $view = recapLayoutView(NetworkRecapLayout::pages(SampleDeck::recap(2), '/sfondo.jpg')[0]->data);
    $price = array_slice(recapLayoutColumnTexts($view, 'price'), 1);
    $texts = array_column($view['texts'], 'text');

    expect($price)->toHaveCount(1)
        ->and($price[0])->toMatchArray(['text' => '83.000,00 €', 'weight' => 900, 'size' => NetworkRecapLayout::FINAL_SIZE, 'strike' => false])
        ->and($texts)->toContain('Totale / stagione', 'Importi IVA esclusa', '38', "47' 30\"", '190');
});

it('D-314: has no price on the club rows: PROPOSTA ECONOMICA is one merged cell over rows and totals', function () {
    $view = recapLayoutView(NetworkRecapLayout::pages(SampleDeck::recap(2), '/sfondo.jpg')[0]->data);
    $priceLeft = NetworkRecapLayout::COLUMNS['price'][0];
    $priceBoxes = array_values(array_filter($view['boxes'], fn (array $box): bool => $box['left'] === $priceLeft));

    // Header cell plus one merged cell from the first row to the bottom of the totals.
    expect($priceBoxes)->toHaveCount(2)
        ->and($priceBoxes[1])->toMatchArray(['top' => 332, 'height' => 3 * 45 + 91 + 1, 'background' => null])
        ->and(recapLayoutColumnTexts($view, 'price'))->toHaveCount(2);
});

it('D-314: writes the original column titles in white on red cells with black borders (defect 1 of §2.4)', function () {
    $view = recapLayoutView(NetworkRecapLayout::pages(SampleDeck::recap(2), '/sfondo.jpg')[0]->data);
    $header = array_values(array_filter($view['texts'], fn (array $text): bool => $text['top'] === NetworkRecapLayout::HEADER_TOP));
    $headerBoxes = array_values(array_filter($view['boxes'], fn (array $box): bool => $box['top'] === NetworkRecapLayout::HEADER_TOP));

    expect(array_column($header, 'text'))->toBe(['CLUB', 'Format', 'No di Match', 'Min. / Partita', 'N.Spot / game', 'PROPOSTA ECONOMICA'])
        ->and(array_unique(array_column($header, 'color')))->toBe(['#FFFFFF'])
        ->and($headerBoxes)->toHaveCount(6)
        ->and(array_unique(array_column($headerBoxes, 'background')))->toBe(['#CC0033'])
        ->and(array_unique(array_column($view['boxes'], 'border')))->toBe(['#000000']);
});

it('D-314: writes the club names in capitals, "—" for a club without LED and nothing in the LED cells of a hospitality row', function () {
    $view = recapLayoutView(NetworkRecapLayout::pages(SampleDeck::recap(5, logosFolder: sys_get_temp_dir()), '/sfondo.jpg')[0]->data);
    $names = array_column(recapLayoutColumnTextsAt($view, NetworkRecapLayout::NAME_LEFT), 'text');
    $rowTexts = fn (int $row): array => array_column(
        array_filter($view['texts'], fn (array $text): bool => $text['top'] === 332 + 45 * $row && $text['left'] !== NetworkRecapLayout::NAME_LEFT),
        'text',
    );

    // Rows: 4 clubs with LED, the fifth without LED, a hospitality row after the second and the fifth.
    expect($names)->toBe(['ATLETICO BORGOVERDE', 'SPORTING VALDARNO', 'HOSPITALITY PREMIUM · 3 POSTI', 'UNIONE CALCIO LAGOMAGGIORE', 'REAL COLLE ALTO', 'POLISPORTIVA RIVA BLU', 'HOSPITALITY PREMIUM · 3 POSTI'])
        ->and($rowTexts(2))->toBe([])
        ->and($rowTexts(5))->toBe(['—', '—', '—', '—'])
        ->and($view['logos'])->toHaveCount(5);
});

it('layout §4.8: places the rows from top 332 every 45 px and the totals right after them', function () {
    $view = recapLayoutView(NetworkRecapLayout::pages(SampleDeck::recap(2), '/sfondo.jpg')[0]->data);
    $tops = array_column(recapLayoutColumnTextsAt($view, NetworkRecapLayout::NAME_LEFT), 'top');
    $total = array_values(array_filter($view['texts'], fn (array $text): bool => $text['text'] === 'Totale / stagione'))[0];
    $vat = array_values(array_filter($view['texts'], fn (array $text): bool => $text['text'] === 'Importi IVA esclusa'))[0];

    // Two clubs and the hospitality row of the second one.
    expect($tops)->toBe([332, 377, 422])
        ->and($total['top'])->toBe(467)
        ->and($total['align'])->toBe('right')
        ->and($total['left'] + $total['width'])->toBe(NetworkRecapLayout::TOTAL_LABEL_RIGHT)
        ->and($vat['top'])->toBe(467 + 91 + 12);
});

it('layout §6.3: fits a long club name into its column', function () {
    $view = recapLayoutView(NetworkRecapLayout::pages(SampleDeck::recap(2, longNames: true), '/sfondo.jpg')[0]->data);
    $name = recapLayoutColumnTextsAt($view, NetworkRecapLayout::NAME_LEFT)[0];

    expect($name['text'])->toEndWith('…')
        ->and($name['size'])->toBe(14.0);
});

/**
 * The texts starting at a given x.
 *
 * @param  array{texts: list<array<string, mixed>>}  $view
 * @return list<array<string, mixed>>
 */
function recapLayoutColumnTextsAt(array $view, int $left): array
{
    return array_values(array_filter($view['texts'], fn (array $text): bool => $text['left'] === $left));
}
