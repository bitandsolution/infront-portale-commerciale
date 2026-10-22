<?php

/*
| Validation messages in Italian (CLAUDE.md §9). Only the rules used so far: every PR that
| uses a new rule adds its message here (otherwise Laravel falls back to English).
*/

return [
    'confirmed' => 'Il campo :attribute non coincide con la conferma.',
    'email' => 'Il campo :attribute deve essere un indirizzo e-mail valido.',
    'max' => [
        'string' => 'Il campo :attribute non può superare :max caratteri.',
    ],
    'min' => [
        'string' => 'Il campo :attribute deve contenere almeno :min caratteri.',
    ],
    'password' => [
        'letters' => 'Il campo :attribute deve contenere almeno una lettera.',
        'mixed' => 'Il campo :attribute deve contenere almeno una lettera maiuscola e una minuscola.',
        'numbers' => 'Il campo :attribute deve contenere almeno un numero.',
        'symbols' => 'Il campo :attribute deve contenere almeno un simbolo.',
        'uncompromised' => 'Questa password compare in archivi di password violate: scegline un\'altra.',
    ],
    'required' => 'Il campo :attribute è obbligatorio.',
    'string' => 'Il campo :attribute deve essere un testo.',
    'unique' => 'Il valore del campo :attribute è già in uso.',

    'attributes' => [
        'first_name' => 'nome',
        'last_name' => 'cognome',
        'email' => 'e-mail',
        'phone' => 'telefono',
        'password' => 'password',
    ],
];
