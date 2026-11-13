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
        'led_mockup_saved' => 'Simulazione LED salvata: :name, :photo',
    ],

    // DM-06: simulation of the logo on the LED (D-398).
    'led_mockups' => [
        'generic' => 'Generica Infront',
        'validation' => [
            'photo_required' => 'Carica la foto del campo.',
            'areas_count_one' => 'Segna l\'area del LED con quattro punti.',
            'areas_count_two' => 'Segna le due aree del LED, fila superiore e fila inferiore, con quattro punti ciascuna.',
            'areas_outside' => 'I punti del LED devono stare dentro la foto.',
        ],
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

    'led_photo_kinds' => [
        'row_1' => '1ª fila',
        'row_2' => '2ª fila',
        'both_rows' => 'Jumbo e Domination',
    ],

    'document_modes' => [
        'complete' => 'Documento completo',
        'summary' => 'Solo riepilogo',
    ],

    'layouts' => [
        'cover' => 'Copertina',
        'network_recap' => 'Recap economico network',
        'club_recap' => 'Recap economico club',
        'led_mockup' => 'Simulazione LED',
        'custom_blocks' => 'Sfondo della slide personalizzata',
    ],

    // Slides composed with blocks (D-399, D-428…D-432; DM-07).
    'custom_slides' => [
        'layouts' => [
            'one_column' => 'Una colonna',
            'two_columns' => 'Due colonne',
            'image_text' => 'Immagine a sinistra, testo a destra',
        ],
        'blocks' => [
            'title' => 'Titolo',
            'text' => 'Testo',
            'table' => 'Tabella',
            'image' => 'Immagine',
            'image_text' => 'Immagine con testo',
        ],
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
        // DM-06 (D-427): the page is skipped, the document is generated anyway.
        'missing_led_mockup' => 'Simulazione LED non configurata per :club (:photo): la pagina non entra nel documento',
        // DM-07 (D-428): the slides composed with blocks need the background of the library.
        'missing_custom_background' => 'Manca lo sfondo attivo della slide personalizzata per il :document: le slide personalizzate non entrano nel documento',
    ],

    // Server command documents:compose.
    'compose_command' => [
        'not_found' => 'Proposta n. :number non trovata.',
        'header' => 'Proposta n. :number · gruppo con :variants variante/i · stagione :season · :document',
        'clubs' => 'Club del gruppo: :clubs',
        'empty' => 'Nessuna slide entra nel documento.',
        'columns' => ['Sezione', 'Ord.', 'Slide', 'Tipo', 'Istanze', 'Obbligatoria', 'Solo riepilogo', 'Nel documento'],
        'yes' => 'sì',
        'no' => '—',
        'custom' => 'Personalizzata',
        'variant' => 'Proposta :letter',
        'warnings' => 'Avvisi:',
        'total' => ':slides slide della libreria, :pages pagine previste.',
        'mode' => 'Contenuto scelto: :mode, :included slide su :slides.',
    ],
];
