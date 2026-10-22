<?php

/*
| Users (M01): texts of the commands and, from PR-04, of the user management pages.
*/

return [
    'fields' => [
        'first_name' => 'Nome',
        'last_name' => 'Cognome',
        'email' => 'E-mail',
        'password' => 'Password (almeno 12 caratteri)',
        'password_confirmation' => 'Ripeti la password',
    ],

    'create_admin' => [
        'intro' => "Creazione del primo admin del portale. L'e-mail è il nome utente.",
        'interactive_only' => 'Il comando è solo interattivo: avvialo da un terminale senza --no-interaction.',
        'already_exists' => 'Esiste già un admin: gli altri utenti si creano dal portale, nella pagina Utenti.',
        'invalid' => 'Dati non validi, nessun utente creato:',
        'created' => 'Admin creato: :email',
        'next_steps' => "Al primo accesso il portale chiederà di configurare l'autenticazione a due fattori.",
    ],
];
