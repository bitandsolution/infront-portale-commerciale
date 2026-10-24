<?php

/*
| Messages of the login (D-214), used by Fortify and by the portal's login steps.
| "failed" is the same for a wrong e-mail and a wrong password: it never reveals whether
| an account exists.
*/

return [
    'failed' => 'E-mail o password non corrette.',
    'password' => 'La password non è corretta.',
    'throttle' => 'Troppi tentativi. Riprova tra :minutes minuti.',

    'two_factor' => [
        'invalid_code' => 'Codice non valido: controlla l\'app di autenticazione e riprova.',
        'invalid_recovery_code' => 'Codice di recupero non valido o già usato.',
    ],
];
