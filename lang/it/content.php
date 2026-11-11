<?php

/*
| Contents (M08): server texts of the slide library and of the composition of the documents (toasts,
| field errors, labels of the enums, server command). The texts of the pages are in lang/it/ui.php
| ("content"). Toast texts never end with a period (ToastRegion).
*/

return [
    'toasts' => [
        'slide_created' => 'Slide :slide caricata',
        'slide_updated' => 'Slide :slide salvata',
        'nothing_changed' => 'Nessuna modifica da salvare',
    ],

    'errors' => [
        'season_closed' => 'La stagione :season è chiusa: la libreria slide è in sola lettura',
        'no_season' => 'Scegli una stagione dalla barra in alto per caricare le slide',
    ],

    // Errors under the fields (they end with a period).
    'validation' => [
        'club_required' => 'Scegli il club della slide.',
        'club_not_in_season' => 'Il club non è un club Infront iscritto a una competizione della stagione.',
        'image_required' => 'Carica l\'immagine della slide (PNG 1920×1080).',
        'recap_network' => 'Il recap network è una slide Infront del documento network.',
        'recap_club' => 'Il recap club è una slide del club per il documento club.',
        'condition_value' => 'Valore non ammesso per la condizione :condition.',
        'condition_boolean' => 'Per :condition scegli Sì oppure No.',
    ],

    'attributes' => [
        'title' => 'titolo',
        'scope' => 'ambito',
        'club_id' => 'club',
        'document_type' => 'tipo di documento',
        'slide_section_id' => 'sezione',
        'sort_order' => 'ordine',
        'layout' => 'tipo di slide',
        'image' => 'immagine',
        'is_active' => 'attiva',
        'conditions' => 'condizioni',
    ],

    'scopes' => [
        'infront' => 'Infront',
        'club' => 'Club',
    ],

    'kinds' => [
        'static' => 'Statica',
        'dynamic' => 'Dinamica',
    ],

    // Document type of a slide (M08 §4.1).
    'document_types' => [
        'network' => 'Network',
        'club' => 'Club',
        'both' => 'Entrambi',
    ],

    // Type of a document (D-121, D-403).
    'document_kinds' => [
        'club' => 'Documento club',
        'network' => 'Documento network',
    ],

    'layouts' => [
        'cover' => 'Copertina',
        'network_recap' => 'Recap economico network',
        'club_recap' => 'Recap economico club',
        'led_mockup' => 'Simulazione LED',
    ],

    'conditions' => [
        'types' => [
            'partnership_tier' => 'Livello partnership',
            'has_partnership' => 'Partnership presente',
            'led_format' => 'Format LED',
            'has_led' => 'LED presente',
            'has_hospitality' => 'Hospitality presente',
            'competition_tier' => 'Competizione',
        ],
        'yes' => 'Sì',
        'no' => 'No',
        'competition_tiers' => [
            '1' => 'Serie A',
            '2' => 'Serie B',
            '3' => 'Serie C',
        ],
    ],

    // Composition of a document (DocumentComposer, D-405).
    'composition' => [
        'no_clubs' => 'Il gruppo non ha club: nessun documento da comporre',
        'no_season' => 'Il gruppo non ha una stagione: nessun documento da comporre',
        'missing_cover' => 'Manca la copertina attiva per il :document',
        'missing_recap' => 'Manca il recap attivo per il :document',
        'missing_closing' => 'Manca una slide attiva nella sezione Chiusura per il :document',
    ],

    // Server command documents:compose.
    'compose_command' => [
        'not_found' => 'Proposta n. :number non trovata.',
        'header' => 'Proposta n. :number · gruppo con :variants variante/i · stagione :season · :document',
        'clubs' => 'Club del gruppo: :clubs',
        'empty' => 'Nessuna slide entra nel documento.',
        'columns' => ['Sezione', 'Ord.', 'Slide', 'Tipo', 'Istanze', 'Obbligatoria', 'Solo riepilogo'],
        'yes' => 'sì',
        'no' => '—',
        'variant' => 'Proposta :letter',
        'warnings' => 'Avvisi:',
        'total' => ':slides slide della libreria, :pages pagine previste.',
    ],
];
