<?php

use App\Support\Documents\DocumentNumbers;

/*
| Numbers in the documents (layout-dinamici §2.2): amounts with the Italian separators and the euro
| sign after a space, LED minutes from whole seconds (C6).
*/

it('writes amounts in the Italian format with two decimals and "€" after a space', function (string $decimal, string $expected) {
    expect(DocumentNumbers::amount($decimal))->toBe($expected);
})->with([
    ['107996.00', '107.996,00 €'],
    ['132328', '132.328,00 €'],
    ['1234567.5', '1.234.567,50 €'],
    ['999.99', '999,99 €'],
    ['0.00', '0,00 €'],
    ['000045.10', '45,10 €'],
]);

it('refuses an amount that is not a decimal string (C5: never float)', function () {
    DocumentNumbers::amount('1,5');
})->throws(InvalidArgumentException::class);

it('writes the LED minutes per match as m\'ss" and the totals as m\' ss"', function () {
    expect(DocumentNumbers::minutesPerMatch(60))->toBe("1'00\"")
        ->and(DocumentNumbers::minutesPerMatch(90))->toBe("1'30\"")
        ->and(DocumentNumbers::minutesPerMatch(30))->toBe("0'30\"")
        ->and(DocumentNumbers::totalMinutes(1710))->toBe("28' 30\"")
        ->and(DocumentNumbers::totalMinutes(108000))->toBe("1.800' 00\"");
});

it('writes whole numbers with the Italian thousands separator', function () {
    expect(DocumentNumbers::integer(1406))->toBe('1.406')
        ->and(DocumentNumbers::integer(38))->toBe('38')
        ->and(DocumentNumbers::integer(1234567))->toBe('1.234.567');
});
