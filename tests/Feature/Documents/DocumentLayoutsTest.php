<?php

use App\Domain\Documents\Layouts\CoverLayout;
use App\Domain\Documents\Layouts\NetworkRecapLayout;
use App\Domain\Documents\Layouts\RecapStyle;
use App\Domain\Documents\Services\TextFitter;
use App\Enums\DocumentLayout;
use App\Enums\DocumentType;
use App\Support\Documents\SampleDeck;

/*
| Layouts of DM-04 (layout-dinamici §4.1, §4.9; D-252, D-396, D-415): the cover with the logo of the
| client and the date, the club recap on the network grid with the colour and the name of the club,
| the title of a variant above a recap. The drawing is checked by tests/Pdf/VisualRegressionTest.
*/

/**
 * @param  array<string, mixed>  $data
 * @return array{boxes: list<array<string, mixed>>, texts: list<array<string, mixed>>, logos: list<array<string, mixed>>}
 */
function layoutsRecapView(array $data): array
{
    return NetworkRecapLayout::viewData($data, app(TextFitter::class), fn (string $path): string => basename($path));
}

beforeEach(function () {
    if (! is_file('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf') || ! function_exists('imagettfbbox')) {
        $this->markTestSkipped('fonts-dejavu-core or GD with FreeType is not installed');
    }

    config(['documents.fonts_path' => null]);
});

it('D-415: the club recap starts at top 453 with the colour of the club and its name over the first column', function () {
    $page = NetworkRecapLayout::pages(SampleDeck::clubRecap(), '/sfondo.jpg', RecapStyle::club('#97989B', 'Udinese Calcio'))[0];
    $view = layoutsRecapView($page->data);
    $header = array_values(array_filter($view['texts'], fn (array $text): bool => $text['top'] === 453));
    $headerBoxes = array_values(array_filter($view['boxes'], fn (array $box): bool => $box['top'] === 453));
    $priceLeft = NetworkRecapLayout::COLUMNS['price'][0];
    $priceBoxes = array_values(array_filter($view['boxes'], fn (array $box): bool => $box['left'] === $priceLeft && $box['background'] === null));

    expect($page->layout)->toBe(DocumentLayout::ClubRecap)
        ->and(array_column($header, 'text'))->toBe(['UDINESE CALCIO', 'Format', 'No di Match', 'Min. / Partita', 'N.Spot / game', 'PROPOSTA ECONOMICA'])
        ->and(array_unique(array_column($headerBoxes, 'background')))->toBe(['#97989B'])
        ->and($headerBoxes[0]['height'])->toBe(106)
        // One merged price cell over the three rows (60 high) and the totals (90 high): no price per row.
        ->and($priceBoxes)->toHaveCount(1)
        ->and($priceBoxes[0])->toMatchArray(['top' => 559, 'height' => 3 * 60 + 90 + 1]);
});

it('D-415: the club recap writes the row names from the left of the first column, without logos', function () {
    $view = layoutsRecapView(NetworkRecapLayout::pages(SampleDeck::clubRecap(), '/sfondo.jpg', RecapStyle::club(null, 'Udinese Calcio'))[0]->data);
    $left = NetworkRecapLayout::COLUMNS['club'][0] + NetworkRecapLayout::CELL_PADDING;
    $names = array_column(array_values(array_filter($view['texts'], fn (array $text): bool => $text['left'] === $left && $text['top'] >= 559)), 'text');

    expect($names)->toBe(['TOP PARTNER', 'LED', 'HOSPITALITY PREMIUM · 2 POSTI'])
        ->and($view['logos'])->toBe([])
        // Without a colour of the club the header keeps the red of the network.
        ->and(array_values(array_filter($view['boxes'], fn (array $box): bool => $box['top'] === 453))[0]['background'])->toBe(NetworkRecapLayout::HEADER_BACKGROUND);
});

it('D-396: the title of a variant sits right-aligned above the price column, in the colour of the header', function () {
    $view = layoutsRecapView(NetworkRecapLayout::pages(SampleDeck::recap(2), '/sfondo.jpg', null, 'Proposta B')[0]->data);
    $title = array_values(array_filter($view['texts'], fn (array $text): bool => $text['text'] === 'Proposta B'));

    expect($title)->toHaveCount(1)
        ->and($title[0])->toMatchArray([
            'left' => NetworkRecapLayout::COLUMNS['price'][0],
            'top' => NetworkRecapLayout::HEADER_TOP - RecapStyle::TITLE_GAP - RecapStyle::TITLE_HEIGHT,
            'align' => 'right',
            'color' => NetworkRecapLayout::HEADER_BACKGROUND,
        ]);
});

it('D-252: the cover writes the date at the top right and the logo in the box of its document type', function () {
    $network = CoverLayout::viewData(CoverLayout::page('/copertina.jpg', DocumentType::Network, '24/05/2027', '/logo.png')->data, fn (string $path): string => basename($path));
    $club = CoverLayout::viewData(CoverLayout::page('/copertina.jpg', DocumentType::Club, '24/05/2027', '/logo.png')->data, fn (string $path): string => basename($path));
    $withoutLogo = CoverLayout::viewData(CoverLayout::page('/copertina.jpg', DocumentType::Network, '24/05/2027', null)->data, fn (string $path): string => basename($path));

    expect($network['texts'][0])->toMatchArray(['text' => '24/05/2027', 'align' => 'right', 'left' => 1849 - CoverLayout::DATE_WIDTH, 'top' => 21, 'color' => '#FFFFFF'])
        ->and($network['logos'][0])->toMatchArray(['src' => 'logo.png', 'left' => 170, 'top' => 706, 'width' => 324, 'height' => 181])
        ->and($club['logos'][0])->toMatchArray(['left' => 37, 'top' => 536, 'width' => 549, 'height' => 181])
        ->and($withoutLogo['logos'])->toBe([]);
});
