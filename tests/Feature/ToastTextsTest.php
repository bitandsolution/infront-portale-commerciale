<?php

/*
| Texts shown in a toast (ux-architettura §4.5): the toast component adds the final period itself
| (ToastRegion.vue), so the texts in lang/it never end with one ("Nessuna modifica da salvare..",
| seen in the review of F1-01).
*/

it('the texts shown in a toast never end with a period', function (string $group) {
    $texts = __($group);
    expect($texts)->toBeArray();

    foreach ((array) $texts as $key => $text) {
        expect($text)->toBeString()
            ->and(str_ends_with((string) $text, '.'))->toBeFalse("{$group}.{$key} ends with a period");
    }
})->with([
    'users.toasts',
    'users.errors',
    'settings.toasts',
    'sports.toasts',
    'sports.errors',
]);
