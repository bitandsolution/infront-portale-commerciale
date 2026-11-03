<?php

use App\Support\LedDuration;

/*
| LED durations of the inventory (C6, D-063; F2-04): seconds per match shown as m′ss″ and typed as
| minutes with a decimal comma.
*/

it('C6: shows seconds per match as minutes and seconds', function (int $seconds, string $expected) {
    expect(LedDuration::format($seconds))->toBe($expected);
})->with([
    [0, '0′00″'],
    [30, '0′30″'],
    [1710, '28′30″'],
    [5400, '90′00″'],
    [-30, '−0′30″'],
]);

it('D-063: reads the minutes typed by the admin, with comma or point', function (string $input, ?int $expected) {
    expect(LedDuration::parseMinutes($input))->toBe($expected);
})->with([
    ['28,5', 1710],
    ['28.5', 1710],
    ['30', 1800],
    [' 0,5 ', 30],
    ['0', 0],
    ['6,25', 375],
    ['6,51', null],
    ['-1', null],
    ['abc', null],
    ['', null],
    ['1.000', 60000],
]);

it('writes the minutes for the input field without useless decimals', function (int $seconds, string $expected) {
    expect(LedDuration::minutesForInput($seconds))->toBe($expected);
})->with([
    [1710, '28,5'],
    [1800, '30'],
    [30, '0,5'],
    [0, '0'],
]);
