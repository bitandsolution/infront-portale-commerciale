<?php

/*
| Interface texts shared with the frontend (HandleInertiaRequests → props.ui).
| Vue components read them with useTrans(); no interface text is written in the code (CLAUDE.md §9).
*/

return [
    'brand' => 'infront',
    'app_name' => 'Portale Commerciale',

    'roles' => [
        'admin' => 'Admin',
        'manager' => 'Gestore',
        'agent' => 'Agente',
    ],

    'menu' => [
        'label' => 'Menu principale',
        'open' => 'Apri il menu',
        'close' => 'Chiudi il menu',
        'expand_group' => 'Mostra le voci di :group',

        'dashboard' => 'Dashboard',
        'profile' => 'Profilo',

        'proposals' => 'Proposte',
        'proposals_new' => 'Nuova proposta',
        'proposals_mine' => 'Le mie proposte',
        'proposals_pending' => 'Da approvare',
        'proposals_all' => 'Tutte',

        'clients' => 'Clienti',
        'clients_mine' => 'I miei clienti',

        'catalog' => 'Catalogo',
        'catalog_led' => 'Listini LED',
        'catalog_partnership' => 'Partnership',
        'catalog_hospitality' => 'Hospitality',

        'inventory' => 'Magazzino',
        'inventory_availability' => 'Disponibilità',
        'inventory_capacity' => 'Capacità',

        'content' => 'Contenuti',
        'content_slides' => 'Libreria slide',
        'content_simulator' => 'Simulatore',
        'content_kpi' => 'KPI e digital',

        'sports' => 'Anagrafiche sportive',
        'sports_calendars' => 'Calendari',
        'sports_import' => 'Import calendari',
        'sports_matches' => 'Partite',
        'sports_stadiums' => 'Stadi',
        'sports_seasons' => 'Stagioni',
        'sports_competitions' => 'Competizioni',
        'sports_clubs' => 'Club',

        'reports' => 'Report',
        'reports_proposals' => 'Dashboard proposte',
        'reports_agents' => 'Proposte per agente',
        'reports_clubs' => 'Proposte per club',
        'reports_inventory' => 'Occupazione magazzino',
        'reports_sectors' => 'Clienti per settore',
        'reports_access' => 'Accessi',

        'audit' => 'Audit',
        'users' => 'Utenti',
        'settings' => 'Impostazioni',
    ],

    'season' => [
        'label' => 'Stagione :season',
        'select' => 'Cambia stagione',
        'unavailable' => 'Il cambio stagione sarà disponibile con le anagrafiche sportive',
        'placeholder' => '2027-28',
    ],

    'auth' => [
        'deactivated' => "Il tuo account è disattivato. Per riattivarlo rivolgiti all'amministratore",
        'session_expired' => 'Sessione scaduta per inattività: accedi di nuovo per continuare',

        'login' => [
            'title' => 'Accedi',
            'intro' => 'Inserisci la tua e-mail aziendale e la password.',
            'email' => 'E-mail',
            'password' => 'Password',
            'submit' => 'Accedi',
            'submitting' => 'Accesso in corso…',
            'locked' => 'Accesso bloccato per troppi tentativi. Potrai riprovare tra :time.',
            'help' => "Password dimenticata o smartphone perso? Rivolgiti all'amministratore del portale.",
        ],

        'challenge' => [
            'title' => 'Verifica in due passaggi',
            'intro' => "Inserisci il codice a 6 cifre che vedi nell'app di autenticazione.",
            'code' => 'Codice di verifica',
            'recovery_intro' => 'Inserisci uno dei codici di recupero che hai salvato. Ogni codice si usa una sola volta.',
            'recovery_code' => 'Codice di recupero',
            'use_recovery' => 'Non hai lo smartphone? Usa un codice di recupero',
            'use_code' => "Usa il codice dell'app di autenticazione",
            'submit' => 'Verifica',
            'submitting' => 'Verifica in corso…',
            'back' => "Torna all'accesso",
        ],

        'setup' => [
            'title' => 'Configura la verifica in due passaggi',
            'intro' => "Per proteggere il portale, ogni accesso richiede anche un codice dall'app di autenticazione dello smartphone (per esempio Google Authenticator o Microsoft Authenticator).",
            'step_scan' => "Apri l'app di autenticazione e inquadra il codice QR.",
            'step_key' => 'Se non puoi inquadrarlo, aggiungi un account inserendo questa chiave:',
            'account' => 'Account: :account',
            'step_confirm' => "Scrivi il codice a 6 cifre che l'app mostra per il portale.",
            'qr_alt' => 'Codice QR da inquadrare con l\'app di autenticazione',
            'code' => 'Codice di verifica',
            'submit' => 'Attiva la verifica',
            'submitting' => 'Verifica in corso…',
            'logout' => 'Esci',
        ],

        'recovery' => [
            'title' => 'Codici di recupero',
            'intro' => 'Se perdi lo smartphone puoi accedere con uno di questi codici, una sola volta ciascuno. Conservali in un luogo sicuro, fuori dallo smartphone.',
            'warning' => 'Questi codici vengono mostrati solo ora: salvali prima di continuare.',
            'copy' => 'Copia i codici',
            'copied' => 'Codici copiati',
            'download' => 'Scarica i codici',
            'file_name' => 'codici-recupero-portale-infront.txt',
            'file_header' => 'Portale Commerciale Infront - codici di recupero (ogni codice vale una volta)',
            'confirm' => 'Ho salvato i codici di recupero',
            'continue' => 'Continua',
        ],
    ],

    'placeholder' => [
        'title' => 'Funzione in arrivo',
        'message' => 'Questa pagina sarà disponibile nelle prossime fasi dello sviluppo. Il menu mostra già le voci previste per il tuo ruolo.',
    ],

    'user' => [
        'area' => 'Utente collegato',
        'menu' => 'Menu utente',
        'logout' => 'Esci',
    ],

    'states' => [
        'loading' => 'Caricamento in corso',
        'empty_title' => 'Nessun elemento',
        'error_title' => 'Impossibile caricare i dati',
        'error_message' => 'Si è verificato un problema durante il caricamento. Riprova tra qualche istante.',
        'retry' => 'Riprova',
    ],

    'toast' => [
        'region' => 'Notifiche',
        'close' => 'Chiudi la notifica',
        'code' => 'codice',
    ],

    'errors' => [
        'system' => "Qualcosa è andato storto. Riprova; se il problema persiste contatta l'amministratore",
        'offline' => 'Connessione assente: controlla la rete e riprova',
        'page_title' => 'Errore del portale',
        'back_home' => 'Torna alla pagina iniziale',
        'forbidden_title' => 'Accesso non consentito',
        'forbidden' => 'Non hai i permessi per questa funzione',
    ],
];
