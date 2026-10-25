<?php

/*
| Validation messages in Italian (CLAUDE.md §9). Only the rules used so far: every PR that
| uses a new rule adds its message here (otherwise Laravel falls back to English).
*/

return [
    'array' => 'Il campo :attribute deve essere un elenco.',
    'after' => 'Il campo :attribute deve essere una data successiva a :date.',
    'between' => [
        'numeric' => 'Il campo :attribute deve essere compreso tra :min e :max.',
    ],
    'boolean' => 'Il campo :attribute deve essere sì o no.',
    'confirmed' => 'Il campo :attribute non coincide con la conferma.',
    'current_password' => 'La password attuale non è corretta.',
    'date_format' => 'Il campo :attribute deve essere una data nel formato :format.',
    'different' => 'I campi :attribute e :other devono essere diversi.',
    'distinct' => 'Il campo :attribute contiene un valore ripetuto.',
    'email' => 'Il campo :attribute deve essere un indirizzo e-mail valido.',
    'file' => 'Il campo :attribute deve essere un file.',
    'enum' => 'Il valore scelto per :attribute non è valido.',
    'exists' => 'Il valore scelto per :attribute non esiste.',
    'integer' => 'Il campo :attribute deve essere un numero intero.',
    'max' => [
        'string' => 'Il campo :attribute non può superare :max caratteri.',
    ],
    'min' => [
        'array' => 'Scegli almeno :min :attribute.',
        'numeric' => 'Il campo :attribute deve essere almeno :min.',
        'string' => 'Il campo :attribute deve contenere almeno :min caratteri.',
    ],
    'password' => [
        'letters' => 'Il campo :attribute deve contenere almeno una lettera.',
        'mixed' => 'Il campo :attribute deve contenere almeno una lettera maiuscola e una minuscola.',
        'numbers' => 'Il campo :attribute deve contenere almeno un numero.',
        'symbols' => 'Il campo :attribute deve contenere almeno un simbolo.',
        'uncompromised' => 'Questa password compare in archivi di password violate: scegline un\'altra.',
    ],
    'regex' => 'Il formato del campo :attribute non è valido.',
    'required' => 'Il campo :attribute è obbligatorio.',
    'string' => 'Il campo :attribute deve essere un testo.',
    'unique' => 'Il valore del campo :attribute è già in uso.',

    'attributes' => [
        'first_name' => 'nome',
        'last_name' => 'cognome',
        'email' => 'e-mail',
        'phone' => 'telefono',
        'role' => 'ruolo',
        'current_password' => 'password attuale',
        'password' => 'password',
        'code' => 'codice di verifica',
        'recovery_code' => 'codice di recupero',
    ],
];
