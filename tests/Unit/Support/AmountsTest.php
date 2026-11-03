<?php

use App\Support\Amounts;

/*
| Amounts in euro (C5, D-336): Italian notation in the forms, exact decimal strings in between.
*/

it('reads amounts in Italian notation as decimal strings with two decimals', function (string $input, string $expected) {
    expect(Amounts::parseItalian($input))->toBe($expected);
})->with([
    ['3.157,89', '3157.89'],
    ['3157,89', '3157.89'],
    ['60.000', '60000.00'],
    ['3.157', '3157.00'],
    ['1,5', '1.50'],
    ['0', '0.00'],
    ['  4.736,84 € ', '4736.84'],
    ['9.999.999.999,99', '9999999999.99'],
]);

it('refuses texts that are not amounts in Italian notation', function (string $input) {
    expect(Amounts::parseItalian($input))->toBeNull();
})->with([
    'dot as decimal separator' => ['3157.89'],
    'three decimals' => ['1,234'],
    'negative' => ['-1'],
    'wrong grouping' => ['1.00.000'],
    'grouping with decimals in the wrong place' => ['12.34,5'],
    'letters' => ['abc'],
    'empty' => [''],
]);

it('writes decimal strings in Italian notation', function (string $decimal, string $expected) {
    expect(Amounts::italian($decimal))->toBe($expected);
})->with([
    ['3157.89', '3.157,89'],
    ['60000', '60.000,00'],
    ['0.5', '0,50'],
    ['999.99', '999,99'],
    ['1234567.5', '1.234.567,50'],
]);

it('D-336: rounds to the nearest cent with 0.5 up, and knows the largest DECIMAL(12,2)', function () {
    expect(Amounts::normalize('59999.905'))->toBe('59999.91')
        ->and(Amounts::normalize('59999.904'))->toBe('59999.90')
        ->and(Amounts::exceedsMax('9999999999.99'))->toBeFalse()
        ->and(Amounts::exceedsMax('10000000000.00'))->toBeTrue();
});
