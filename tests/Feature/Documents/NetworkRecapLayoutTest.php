<?php

use App\Domain\Documents\Data\FittedText;
use App\Domain\Documents\Data\RecapRow;
use App\Domain\Documents\Data\RecapTotals;
use App\Domain\Documents\Layouts\NetworkRecapLayout;
use App\Domain\Documents\Services\TextFitter;
use App\Enums\DocumentLayout;
use App\Support\Documents\SampleDeck;

/*
| Network recap (layout-dinamici §4.8, D-103, D-254): at most 10 rows per slide with continuation,
| totals only on the last slide, list total · discount · final total on separate lines, readable
| headers. Sample data from SampleDeck (D-309).
*/

/**
 * View data of one page of the recap, with the logos referenced by their file name.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function recapLayoutView(array $data): array
{
    return NetworkRecapLayout::viewData($data, app(TextFitter::class), fn (string $path): string => basename($path));
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
});

it('layout §4.8: keeps a hospitality row on the slide of its club', function () {
    foreach (NetworkRecapLayout::pages(SampleDeck::recap(12), '/sfondo.jpg') as $page) {
        /** @var list<RecapRow> $rows */
        $rows = $page->data['rows'];

        expect($rows[0]->isHospitality)->toBeFalse();
    }
});

it('D-103: shows list total struck through, discount and final total on three separate lines', function () {
    $pages = NetworkRecapLayout::pages(SampleDeck::recap(3, SampleDeck::DISCOUNT_PERCENT), '/sfondo.jpg');
    $lines = recapLayoutView($pages[0]->data)['totals']['price']['lines'];

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toMatchArray(['text' => '126.000,00 €', 'strike' => true])
        ->and($lines[1])->toMatchArray(['text' => 'Sconto 15%', 'strike' => false])
        ->and($lines[2])->toMatchArray(['text' => '107.100,00 €', 'weight' => 900, 'strike' => false]);
});

it('D-103: shows only the final total, in Black, when there is no discount', function () {
    $pages = NetworkRecapLayout::pages(SampleDeck::recap(2), '/sfondo.jpg');
    $view = recapLayoutView($pages[0]->data);

    expect($view['totals']['price']['lines'])->toBe([
        ['text' => '83.000,00 €', 'size' => NetworkRecapLayout::FINAL_SIZE, 'weight' => 900, 'strike' => false],
    ])
        ->and($view['vatNote']['text'])->toBe('Importi IVA esclusa')
        ->and($view['totals']['cells'][0]['text']->text)->toBe('38')
        ->and($view['totals']['cells'][1]['text']->text)->toBe("47' 30\"")
        ->and($view['totals']['cells'][2]['text']->text)->toBe('190');
});

it('layout §4.8: writes readable white headers in Black on the red band (defect 1 of §2.4)', function () {
    $header = recapLayoutView(NetworkRecapLayout::pages(SampleDeck::recap(2), '/sfondo.jpg')[0]->data)['header'];

    expect($header['color'])->toBe('#FFFFFF')
        ->and($header['background'])->toBe('#CC0033')
        ->and(array_map(fn (array $cell): string => $cell['text']->text, $header['cells']))
        ->toBe(['Club', 'Format', 'N. match', 'Min./partita', 'N. spot/partita', 'Proposta economica'])
        ->and(array_unique(array_column($header['cells'], 'weight')))->toBe([900]);
});

it('layout §4.8: writes "—" for a club without LED and leaves empty the LED cells of a hospitality row', function () {
    $rows = recapLayoutView(NetworkRecapLayout::pages(SampleDeck::recap(5), '/sfondo.jpg')[0]->data)['rows'];
    $texts = array_map(
        fn (array $row): array => array_map(fn (array $cell): string => $cell['text']->text, $row['cells']),
        $rows,
    );

    // Rows: 4 clubs with LED, one hospitality after the second club, the fifth club without LED.
    expect($texts[2])->toBe(['Hospitality Premium × 3', '', '', '', '', '4.500,00 €'])
        ->and($texts[5])->toBe(['Polisportiva Riva Blu', '—', '—', '—', '—', '45.000,00 €'])
        ->and($rows[2]['logo'])->toBeNull()
        ->and($rows[2]['cells'][0]['text']->size)->toBe((float) NetworkRecapLayout::HOSPITALITY_SIZE);
});

it('layout §4.8: places the rows from top 332 every 45 px and the totals right after them', function () {
    $view = recapLayoutView(NetworkRecapLayout::pages(SampleDeck::recap(2), '/sfondo.jpg')[0]->data);

    // Two clubs and the hospitality row of the second one.
    expect(array_column($view['rows'], 'top'))->toBe([332, 377, 422])
        ->and($view['totals']['top'])->toBe(467)
        ->and($view['vatNote']['top'])->toBe(467 + 90 + 12);
});

it('layout §6.3: fits a long club name into its column', function () {
    $view = recapLayoutView(NetworkRecapLayout::pages(SampleDeck::recap(2, longNames: true), '/sfondo.jpg')[0]->data);
    /** @var FittedText $name */
    $name = $view['rows'][0]['cells'][0]['text'];

    expect($name->text)->toEndWith('…')
        ->and($name->size)->toBe(14.0);
});
