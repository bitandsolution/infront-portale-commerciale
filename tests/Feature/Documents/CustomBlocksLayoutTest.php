<?php

use App\Domain\Content\Data\CustomSlide;
use App\Domain\Content\Data\CustomText;
use App\Domain\Content\Services\CustomSlideContent;
use App\Domain\Documents\Layouts\CustomBlocksLayout;
use App\Domain\Documents\Services\TextFitter;
use App\Enums\CustomSlideLayout;

/*
| Layout of the slides composed with blocks (D-399, D-430, D-431; DM-07; layout-dinamici §4.11): the
| text parsed into paragraphs, bullets and bold; the blocks placed in the content area with the
| substitute font (DejaVu, D-311); the content reduced down to 70% and then cut with "…"; every datum
| escaped by the view. The pictures are checked by the visual regression (tests/Pdf).
*/

/**
 * @param  list<array<string, mixed>>  $blocks
 */
function cblSlide(array $blocks, CustomSlideLayout $layout = CustomSlideLayout::OneColumn, ?int $imageFileId = null): CustomSlide
{
    return new CustomSlide(1, 'Prova', $layout, 'BRAND_EXPOSURE', $imageFileId, CustomSlideContent::normalize($blocks));
}

it('D-430: the text is parsed into paragraphs, bulleted items and bold runs, never HTML', function () {
    $text = CustomText::parse("\nUn pacchetto **su misura** per <b>voi</b>\n- LED\n• Hospitality **Premium\n\nFine\n\n");

    expect(array_map(fn ($paragraph): array => [$paragraph->bullet, $paragraph->runs], $text->paragraphs))->toBe([
        [false, [['text' => 'Un pacchetto ', 'bold' => false], ['text' => 'su misura', 'bold' => true], ['text' => ' per <b>voi</b>', 'bold' => false]]],
        [true, [['text' => 'LED', 'bold' => false]]],
        [true, [['text' => 'Hospitality **Premium', 'bold' => false]]],
        [false, []],
        [false, [['text' => 'Fine', 'bold' => false]]],
    ]);
});

it('D-431: a short content stays at 100%, from the top left of the content area', function () {
    $arrangement = CustomBlocksLayout::arrange(cblSlide([
        ['key' => 'a', 'type' => 'title', 'text' => 'La nostra proposta'],
        ['key' => 'b', 'type' => 'text', 'text' => "Testo\n- Punto"],
    ]), [], app(TextFitter::class));

    [$left, $top] = CustomBlocksLayout::AREA;
    $first = $arrangement->elements[0];

    expect($arrangement->overflow)->toBeFalse()
        ->and($arrangement->scale)->toBe(1.0)
        ->and([$first['left'], $first['top'], $first['size']])->toBe([(float) $left, (float) $top, (float) CustomBlocksLayout::TITLE_SIZE])
        ->and($first['runs'])->toBe([['text' => 'La nostra proposta', 'weight' => 900]])
        ->and(array_column(array_filter($arrangement->elements, fn (array $element): bool => $element['marker'] ?? false), 'runs'))->toBe([[['text' => '•', 'weight' => 400]]]);
});

it('D-431: more content is reduced in steps of 5% down to 70%, then cut at the bottom with "…"', function () {
    $long = str_repeat('Un testo molto lungo che occupa la slide per intero e va oltre lo spazio disponibile. ', 11);
    $fitter = app(TextFitter::class);

    $reduced = CustomBlocksLayout::arrange(cblSlide([
        ['key' => 'a', 'type' => 'text', 'text' => $long],
        ['key' => 'b', 'type' => 'text', 'text' => $long],
        ['key' => 'c', 'type' => 'text', 'text' => $long],
    ]), [], $fitter);

    expect($reduced->overflow)->toBeFalse()
        ->and($reduced->scale)->toBeLessThan(1.0)
        ->and($reduced->scale)->toBeGreaterThanOrEqual(CustomBlocksLayout::MIN_SCALE);

    $cut = CustomBlocksLayout::arrange(cblSlide([
        ['key' => 'a', 'type' => 'text', 'text' => $long],
        ['key' => 'b', 'type' => 'text', 'text' => $long],
        ['key' => 'c', 'type' => 'text', 'text' => $long],
        ['key' => 'd', 'type' => 'text', 'text' => $long],
    ]), [], $fitter);

    [, $top, , $height] = CustomBlocksLayout::AREA;
    $lines = array_values(array_filter($cut->elements, fn (array $element): bool => $element['kind'] === 'line'));
    $last = $lines[array_key_last($lines)];
    $runs = $last['runs'];

    expect($cut->overflow)->toBeTrue()
        ->and($cut->scale)->toBe(CustomBlocksLayout::MIN_SCALE)
        ->and(max(array_map(fn (array $element): float => $element['top'] + $element['height'], $cut->elements)))->toBeLessThanOrEqual($top + $height + 0.5)
        ->and(str_ends_with($runs[array_key_last($runs)]['text'], '…'))->toBeTrue();
});

it('D-399: two columns place the blocks on their side; with "image_text" the image of the slide is on the left', function () {
    $fitter = app(TextFitter::class);
    [$left, , $width] = CustomBlocksLayout::AREA;

    $columns = CustomBlocksLayout::arrange(cblSlide([
        ['key' => 'a', 'type' => 'text', 'text' => 'Sinistra', 'column' => 'left'],
        ['key' => 'b', 'type' => 'image', 'file_id' => 9, 'column' => 'right'],
    ], CustomSlideLayout::TwoColumns), [9 => [1600, 900]], $fitter);

    $columnWidth = intdiv($width - CustomBlocksLayout::COLUMN_GAP, 2);
    $image = collect($columns->elements)->firstWhere('kind', 'image');

    expect($columns->elements[0]['left'])->toBe((float) $left)
        ->and($image['left'])->toBe($left + $columnWidth + CustomBlocksLayout::COLUMN_GAP)
        ->and($image['height'])->toBeLessThanOrEqual((float) CustomBlocksLayout::IMAGE_MAX_HEIGHT);

    $imageText = CustomBlocksLayout::arrange(cblSlide([
        ['key' => 'a', 'type' => 'text', 'text' => 'Testo a destra'],
    ], CustomSlideLayout::ImageText, 7), [7 => [800, 1200]], $fitter);

    $slideImage = collect($imageText->elements)->firstWhere('kind', 'image');
    $text = collect($imageText->elements)->firstWhere('kind', 'line');

    expect($slideImage['file_id'])->toBe(7)
        ->and($slideImage['left'])->toBe($left)
        ->and($text['left'])->toBeGreaterThan($left + $width * CustomBlocksLayout::SLIDE_IMAGE_SHARE);
});

it('D-176: the view escapes every datum of the blocks', function () {
    $view = CustomBlocksLayout::viewData(CustomBlocksLayout::page(null, cblSlide([
        ['key' => 'a', 'type' => 'text', 'text' => '<script>alert(1)</script> **<b>grassetto</b>**'],
        ['key' => 'b', 'type' => 'table', 'rows' => [['<i>Club</i>'], ['Lazio']]],
    ]), [])->data, app(TextFitter::class), fn (string $path): string => basename($path));

    $html = view('documents.layouts.custom-blocks', $view)->render();

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;')
        ->and($html)->toContain('&lt;i&gt;Club&lt;/i&gt;')
        ->and($html)->toContain('font-weight: 700;">&lt;b&gt;grassetto&lt;/b&gt;');
});
