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
    ],

    'placeholder' => [
        'title' => 'Funzione in arrivo',
        'message' => 'Questa pagina sarà disponibile nelle prossime fasi dello sviluppo. Il menu mostra già le voci previste per il tuo ruolo.',
    ],

    'dev_login' => [
        'title' => 'Accesso di sviluppo',
        'notice' => "Solo nell'ambiente locale: entra come un utente senza password per provare menu e permessi. Sparisce con l'accesso vero (PR-03).",
        'open' => 'Accesso di sviluppo',
        'change_user' => 'Cambia utente',
        'empty' => 'Nessun utente nel database. Esegui "sail artisan migrate:fresh --seed" e "sail artisan users:create-admin".',
        'name' => 'Nome',
        'email' => 'E-mail',
        'role' => 'Ruolo',
        'status' => 'Stato',
        'active' => 'Attivo',
        'inactive' => 'Disattivato',
        'current' => 'In uso',
        'sign_in' => 'Entra',
        'sign_in_as' => 'Entra come :name',
        'sign_out' => 'Esci',
    ],

    'user' => [
        'area' => 'Utente collegato',
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

    'preview' => [
        'title' => 'Anteprima del layout',
        'notice' => "Pagina di anteprima, visibile solo nell'ambiente locale: utente, stagione e voci di menu sono segnaposto fino all'arrivo di utenti e anagrafiche.",
        'role' => 'Menu del ruolo',
        'user_name' => 'Utente di prova',
        'user_initials' => 'UP',
        'season' => '2027-28',
        'states_title' => 'Stati dell\'interfaccia',
        'state_content' => 'Contenuto',
        'state_empty' => 'Vuoto',
        'state_loading' => 'Caricamento',
        'state_error' => 'Errore',
        'content_text' => 'Qui compariranno i contenuti della pagina selezionata nel menu.',
        'empty_title' => 'Nessuna proposta ancora',
        'empty_message' => 'Crea la tua prima proposta: scegli il cliente, i club e il pacchetto. Il documento viene generato dopo l\'approvazione.',
        'empty_action' => 'Crea la tua prima proposta',
        'toast_title' => 'Notifiche',
        'toast_server' => 'Simula un errore di sistema',
        'toast_offline' => 'Simula la connessione assente',
        'toast_success' => 'Mostra una conferma',
        'toast_success_message' => 'Operazione completata',
        'open' => 'Apri l\'anteprima del layout',
    ],
];
