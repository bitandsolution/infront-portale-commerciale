<?php

/*
|--------------------------------------------------------------------------
| Password hashing (D-214, progettazione-tecnica §5.4.2)
|--------------------------------------------------------------------------
|
| Argon2id for every password. The driver is fixed here and not read from the
| environment, so no server can fall back to another algorithm by mistake.
| "verify" rejects a stored hash made with a different algorithm.
|
| The cost parameters can be lowered only to speed up the automated tests
| (phpunit.xml); staging and production keep the defaults below.
|
*/

return [

    'driver' => 'argon2id',

    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 65536),
        'threads' => (int) env('ARGON_THREADS', 1),
        'time' => (int) env('ARGON_TIME', 4),
        'verify' => true,
    ],

    'rehash_on_login' => true,

];
