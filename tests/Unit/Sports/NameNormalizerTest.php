<?php

use App\Support\Sports\NameNormalizer;

// F-11, D-319: names written differently in the calendar files are recognized as the same club.
it('normalizes case, accents, punctuation and spaces', function (string $name, string $expected) {
    expect(NameNormalizer::normalize($name))->toBe($expected);
})->with([
    'punctuation' => ['S.S. Lazio', 'S S LAZIO'],
    'lower case and spaces' => ['  ss   lazio ', 'SS LAZIO'],
    'accents' => ['Società Sportiva Città', 'SOCIETA SPORTIVA CITTA'],
    'apostrophe and dash' => ["Calcio d'Arezzo-1923", 'CALCIO D AREZZO 1923'],
    'only symbols' => ['--- ...', ''],
]);
