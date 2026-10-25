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
        'two_factor_reset' => 'La verifica in due passaggi del tuo account è stata azzerata: accedi di nuovo per configurarla',

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
        'profile' => 'Profilo',
        'logout' => 'Esci',
    ],

    'common' => [
        'save' => 'Salva',
        'saving' => 'Salvataggio in corso…',
        'cancel' => 'Annulla',
        'close' => 'Chiudi',
        'never' => 'Mai',
        'required_note' => 'Tutti i campi sono obbligatori, tranne quelli indicati come facoltativi.',
    ],

    // Users page (M01 §5, D-139): admin only.
    'users' => [
        'title' => 'Utenti',
        'new' => 'Nuovo utente',
        'count' => ':count utenti',
        'search' => 'Cerca per nome o e-mail',
        'filter_role' => 'Ruolo',
        'filter_status' => 'Stato',
        'all_roles' => 'Tutti i ruoli',
        'all_statuses' => 'Tutti gli stati',
        'empty_title' => 'Nessun utente trovato',
        'empty_message' => 'Nessun utente corrisponde ai filtri scelti.',
        'open' => 'Apri la scheda di :name',

        'columns' => [
            'name' => 'Nome',
            'role' => 'Ruolo',
            'status' => 'Stato',
            'two_factor' => 'Verifica in due passaggi',
            'last_login' => 'Ultimo accesso',
        ],

        'status' => [
            'active' => 'Attivo',
            'pending' => 'In attesa di attivazione',
            'inactive' => 'Disattivato',
        ],

        'two_factor' => [
            'on' => 'Configurata',
            'off' => 'Da configurare',
        ],

        'fields' => [
            'first_name' => 'Nome',
            'last_name' => 'Cognome',
            'email' => 'E-mail (nome utente)',
            'phone' => 'Telefono (facoltativo)',
            'role' => 'Ruolo',
        ],

        'create' => [
            'title' => 'Nuovo utente',
            'intro' => "L'utente riceverà per e-mail un link valido 48 ore per scegliere la password e configurare la verifica in due passaggi. Finché non lo usa, l'account resta in attesa di attivazione.",
            'submit' => 'Crea e invia il link',
            'submitting' => 'Creazione in corso…',
            'back' => 'Torna agli utenti',
        ],

        'show' => [
            'back' => 'Torna agli utenti',
            'created_at' => 'Creato il :date',
            'details' => 'Dati e ruolo',
            'save' => 'Salva i dati',
            'self_note' => 'Questo è il tuo account: ruolo, stato e accesso non si modificano da questa pagina. Per nome, telefono e password usa il profilo.',
            'open_profile' => 'Apri il profilo',
            'role_note' => 'Il ruolo decide le funzioni del portale che l\'utente vede e può usare.',

            'access' => 'Accesso',
            'two_factor' => 'Verifica in due passaggi',
            'last_login' => 'Ultimo accesso',
            'activated_at' => 'Attivato il',
            'reset_password' => 'Azzera la password',
            'reset_password_help' => "La password attuale smette subito di funzionare e le sessioni aperte si chiudono. L'utente riceve un link per sceglierne una nuova, che conferma con il codice dell'app.",
            'reset_two_factor' => 'Azzera la verifica in due passaggi',
            'reset_two_factor_help' => "Per esempio se l'utente ha perso lo smartphone. Password e verifica smettono di funzionare e le sessioni aperte si chiudono: l'utente riceve un link per impostarle di nuovo.",
            'resend_link' => 'Invia di nuovo il link',
            'resend_link_help' => "L'utente non ha ancora impostato password e verifica in due passaggi. Il nuovo link vale 48 ore; quello inviato prima smette di funzionare.",

            'status' => 'Stato',
            'active_text' => "L'utente può accedere al portale.",
            'pending_text' => "L'utente non ha ancora usato il link di attivazione.",
            'inactive_text' => 'Disattivato il :date.',
            'inactive_by_text' => 'Disattivato il :date da :name.',
            'deactivate' => 'Disattiva utente',
            'deactivate_help' => "L'utente non potrà più accedere e le sue sessioni aperte si chiudono. I suoi dati restano nel portale.",
            'deactivate_agent_help' => 'Le sue proposte restano a suo nome. La riassegnazione dei suoi clienti sarà disponibile con la gestione dei clienti.',
            'reactivate' => 'Riattiva utente',
            'reactivate_help' => "L'utente potrà accedere di nuovo con la sua password e la sua verifica in due passaggi.",
        ],

        'confirm' => [
            'deactivate_title' => 'Disattivare :name?',
            'deactivate' => 'Disattiva',
            'reset_password_title' => 'Azzerare la password di :name?',
            'reset_password' => 'Azzera e invia il link',
            'reset_two_factor_title' => 'Azzerare la verifica in due passaggi di :name?',
            'reset_two_factor' => 'Azzera e invia il link',
            'link_to' => 'Il link arriverà a :email.',
        ],
    ],

    // Personal profile (D-267): every role, from the user menu of the top bar.
    'profile' => [
        'title' => 'Profilo',
        'details' => 'Dati personali',
        'email_note' => "L'e-mail è il tuo nome utente: per cambiarla rivolgiti all'amministratore del portale.",
        'role' => 'Ruolo',
        'last_login' => 'Ultimo accesso',
        'save' => 'Salva i dati',

        'password' => 'Cambia password',
        'password_intro' => 'Dopo il cambio le altre sessioni aperte con il tuo account si chiudono; questa resta attiva.',
        'current_password' => 'Password attuale',
        'new_password' => 'Nuova password (almeno 12 caratteri)',
        'new_password_confirmation' => 'Ripeti la nuova password',
        'password_submit' => 'Cambia password',
    ],

    // Pages of the access links sent by e-mail (D-277…D-280).
    // Settings page (PR-07; D-191, D-286): admin only. Labels and descriptions of the single
    // settings come from the server (lang/it/settings.php).
    'settings' => [
        'intro' => "Valori di sistema usati da configuratore, magazzino e audit. Le modifiche valgono da subito e restano registrate nell'audit.",
        'fixed' => 'Fisso',
        'default_value' => 'Valore predefinito',
        'range' => 'Valori ammessi: da :min a :max :unit.',
        'save' => 'Salva le impostazioni',
    ],

    'access' => [
        'activate_title' => 'Attiva il tuo account',
        'renewal_title' => 'Configura di nuovo il tuo accesso',
        'activate_intro' => 'Ciao :name, scegli la password e configura la verifica in due passaggi con l\'app di autenticazione dello smartphone (per esempio Google Authenticator o Microsoft Authenticator). Password e verifica vengono salvate insieme, quando il codice è corretto.',
        'step_password' => 'Scegli la password',
        'password' => 'Password (almeno 12 caratteri)',
        'password_confirmation' => 'Ripeti la password',
        'step_app' => "Configura l'app di autenticazione",
        'step_code' => "Conferma con il codice a 6 cifre che l'app mostra per il portale",
        'code' => 'Codice di verifica',
        'activate_submit' => 'Attiva e accedi',
        'submitting' => 'Verifica in corso…',

        'reset_title' => 'Scegli una nuova password',
        'reset_intro' => "Ciao :name, scegli la nuova password e confermala con il codice dell'app di autenticazione.",
        'reset_submit' => 'Salva la password e accedi',

        'invalid_title' => 'Link non valido',
        'invalid_message' => "Il link è scaduto, è già stato usato oppure non è completo. Chiedi all'amministratore del portale di inviarne uno nuovo.",
        'to_login' => "Vai all'accesso",
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
