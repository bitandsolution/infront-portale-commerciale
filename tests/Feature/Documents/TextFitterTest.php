<?php

use App\Domain\Documents\Services\TextFitter;
use App\Enums\DocumentFontWeight;

/*
| Long texts in the layouts (layout-dinamici §6.3): font reduced down to 70%, then the text is cut
| with "…"; never wider than its box. Measured with the DejaVu substitute (D-311).
*/

beforeEach(function () {
    if (! is_file('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf') || ! function_exists('imagettfbbox')) {
        $this->markTestSkipped('fonts-dejavu-core or GD with FreeType is not installed');
    }

    config(['documents.fonts_path' => null]);
});

it('layout §6.3: keeps the size of a text that fits its box', function () {
    $fitted = app(TextFitter::class)->fit('Atletico Borgoverde', DocumentFontWeight::Bold, 20, 383);

    expect($fitted->text)->toBe('Atletico Borgoverde')
        ->and($fitted->size)->toBe(20.0);
});

it('layout §6.3: reduces the size, not below 70%, before cutting the text', function () {
    $fitter = app(TextFitter::class);
    $fitted = $fitter->fit('Unione Calcio Lagomaggiore e Valtorre', DocumentFontWeight::Bold, 20, 383);

    expect($fitted->text)->toBe('Unione Calcio Lagomaggiore e Valtorre')
        ->and($fitted->size)->toBeLessThan(20.0)
        ->and($fitted->size)->toBeGreaterThanOrEqual(14.0)
        ->and($fitter->width($fitted->text, DocumentFontWeight::Bold, $fitted->size))->toBeLessThanOrEqual(383.0);
});

it('layout §6.3: cuts with "…" at 70% a text too long even when reduced', function () {
    $fitter = app(TextFitter::class);
    $fitted = $fitter->fit(
        'Associazione Sportiva Dilettantistica Unione Calcio Borgoverde e Valle del Torrente Alto',
        DocumentFontWeight::Bold,
        20,
        383,
    );

    expect($fitted->size)->toBe(14.0)
        ->and($fitted->text)->toEndWith('…')
        ->and($fitted->text)->toStartWith('Associazione Sportiva')
        ->and($fitter->width($fitted->text, DocumentFontWeight::Bold, $fitted->size))->toBeLessThanOrEqual(383.0);
});

it('layout §6.3: collapses spaces and line breaks, so a text never goes on two lines', function () {
    $fitted = app(TextFitter::class)->fit("Real   Colle\nAlto", DocumentFontWeight::Bold, 20, 383);

    expect($fitted->text)->toBe('Real Colle Alto');
});
