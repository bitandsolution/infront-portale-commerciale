<?php

/*
| Users (M01): texts of the commands, of the e-mails with the access links and of the messages of
| the user management. The texts of the pages are in ui.php (users, profile, access).
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

    'reset_two_factor' => [
        'not_found' => 'Nessun utente con e-mail :email.',
        'none' => "L'utente :email non ha la verifica in due passaggi configurata: nessuna modifica.",
        'summary' => 'Utente: :name (:email), :role.',
        'confirm' => "Azzerare la verifica in due passaggi? L'utente la riconfigurerà al prossimo accesso e le sue sessioni aperte verranno chiuse.",
        'cancelled' => 'Operazione annullata: nessuna modifica.',
        'done' => 'Verifica in due passaggi azzerata per :email. Al prossimo accesso con la password il portale chiederà di configurarla di nuovo.',
    ],

    'access' => [
        'invalid' => "Il link non è più valido: chiedi all'amministratore di inviarne uno nuovo.",
    ],

    'errors' => [
        'last_active_admin' => 'Operazione non consentita: deve restare almeno un admin attivo. Nomina prima un altro admin.',
        'own_account' => 'Non puoi eseguire questa operazione sul tuo account: per i tuoi dati usa il profilo.',
        'inactive' => "L'utente è disattivato: riattivalo prima di procedere.",
        'two_factor_not_configured' => "L'utente non ha ancora configurato la verifica in due passaggi: invia di nuovo il link di accesso.",
        'already_configured' => "L'utente ha già configurato l'accesso: usa il reset della password o della verifica in due passaggi.",
    ],

    'toasts' => [
        'created' => 'Utente creato: il link di attivazione è stato inviato a :email',
        'updated' => 'Dati dell\'utente salvati',
        'deactivated' => ':name è stato disattivato',
        'reactivated' => ':name è stato riattivato',
        'two_factor_reset' => 'Verifica in due passaggi azzerata: il link per configurare di nuovo l\'accesso è stato inviato a :email',
        'password_reset' => 'Password azzerata: il link per sceglierne una nuova è stato inviato a :email',
        'link_sent' => 'Nuovo link di accesso inviato a :email',
        'profile_updated' => 'Dati personali salvati',
        'password_changed' => 'Password aggiornata: le altre sessioni aperte sono state chiuse',
        'password_set' => 'Nuova password impostata: bentornato nel portale',
    ],

    // E-mails with the access links (temporary text until PR-08, D-277).
    'mail' => [
        'greeting' => 'Ciao :name,',
        'expiry' => 'Il link vale :hours ore e si può usare una sola volta.',
        'ignore' => "Se non ti aspettavi questo messaggio, ignoralo e avvisa l'amministratore del portale.",

        'activation' => [
            'subject' => 'Attiva il tuo account del Portale Commerciale Infront',
            'intro' => "L'amministratore ti ha creato un account nel Portale Commerciale Infront. Per attivarlo scegli la password e configura la verifica in due passaggi con l'app di autenticazione dello smartphone.",
            'action' => "Attiva l'account",
        ],
        'two_factor_reset' => [
            'subject' => 'Configura di nuovo il tuo accesso al Portale Commerciale Infront',
            'intro' => "L'amministratore ha azzerato la verifica in due passaggi del tuo account. Per entrare di nuovo scegli una nuova password e configura l'app di autenticazione dello smartphone.",
            'action' => "Configura l'accesso",
        ],
        'password_reset' => [
            'subject' => 'Scegli una nuova password per il Portale Commerciale Infront',
            'intro' => "L'amministratore ha azzerato la password del tuo account. Scegline una nuova e confermala con il codice dell'app di autenticazione.",
            'action' => 'Scegli la nuova password',
        ],
    ],
];
