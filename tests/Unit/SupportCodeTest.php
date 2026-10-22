<?php

use App\Support\SupportCode;

// D-265: 6 characters, no ambiguous 0/O/1/I, one stable value per request.
it('D-265: generates 6-character codes without ambiguous characters', function () {
    foreach (range(1, 200) as $i) {
        expect(SupportCode::generate())->toMatch(SupportCode::pattern())
            ->not->toMatch('/[01OI]/');
    }
});

it('D-265: keeps the same code for the whole request', function () {
    $code = new SupportCode;

    expect($code->value())->toBe($code->value());
});
