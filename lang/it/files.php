<?php

/*
| Files (C10, D-218): names of purposes and types, messages for refused files, texts of the server
| commands files:prune-orphans and files:try.
*/

return [
    'purposes' => [
        'client_logo' => 'Logo del cliente',
        'club_logo' => 'Logo del club',
        'slide_image' => 'Immagine della slide',
        'calendar_import' => 'Calendario Excel',
        'generated_pdf' => 'Documento PDF',
    ],

    'types' => [
        'png' => 'PNG',
        'jpeg' => 'JPG',
        'xlsx' => 'Excel (.xlsx)',
        'pdf' => 'PDF',
    ],

    'types_separator' => ' o ',

    'errors' => [
        'empty' => 'Il file è vuoto.',
        'too_large' => 'Il file pesa :size MB: il massimo è :max MB.',
        'type_not_allowed' => 'Formato non ammesso: carica un file :types.',
        'unreadable' => 'Il file è danneggiato o non si riesce a leggere.',
        'cmyk' => 'L\'immagine JPG è in CMYK: salvala in RGB e caricala di nuovo.',
        'wrong_dimensions' => 'L\'immagine misura :width×:height pixel: deve essere esattamente :required_width×:required_height.',
        'too_many_pixels' => 'L\'immagine misura :width×:height pixel: il lato più lungo può essere al massimo :max pixel.',
        'too_heavy_to_process' => 'L\'immagine è troppo grande da elaborare: riducila e caricala di nuovo.',
        'macros' => 'Il file Excel contiene macro: salvalo come .xlsx senza macro e caricalo di nuovo.',
        'not_xlsx' => 'Il file non è un Excel .xlsx valido: salvalo come "Cartella di lavoro di Excel (.xlsx)" e caricalo di nuovo.',
        'too_large_unpacked' => 'Il file Excel è troppo grande una volta aperto.',
    ],

    'console' => [
        'prune_done' => 'File orfani eliminati: :count.',
        'prune_dry_run' => 'File orfani da eliminare (prova, nessuna cancellazione): :count.',
        'prune_file' => '- :path',
        'try_only_local' => 'Comando disponibile solo nell\'ambiente di sviluppo (APP_ENV=local o testing).',
        'try_intro' => 'Prova del servizio di archiviazione con file generati sul momento (PR-06).',
        'try_accepted' => 'Accettato',
        'try_refused' => 'Rifiutato',
        'try_expected_ok' => 'come previsto',
        'try_expected_ko' => 'NON come previsto',
        'try_summary' => 'Casi come previsto: :ok su :total.',
        'try_columns' => [
            'case' => 'Caso',
            'outcome' => 'Esito',
            'detail' => 'Id o messaggio',
            'mime' => 'MIME',
            'pixels' => 'Pixel',
            'size' => 'Byte',
            'sha256' => 'SHA-256 (inizio)',
        ],
        'try_cases' => [
            'logo_png' => 'Logo cliente PNG 600×300 con metadati',
            'logo_jpeg_exif' => 'Logo cliente JPG con EXIF',
            'logo_polyglot' => 'Logo cliente PNG con codice PHP in coda',
            'logo_3mb' => 'Logo cliente da 3 MB',
            'logo_pdf' => 'PDF al posto del logo',
            'logo_php' => 'File PHP rinominato logo.png',
            'logo_html' => 'File HTML rinominato logo.png',
            'logo_svg' => 'Logo SVG',
            'slide_small' => 'Slide PNG 1600×900',
            'slide_ok' => 'Slide PNG 1920×1080',
            'slide_jpeg' => 'Slide JPG 1920×1080',
            'xlsx_ok' => 'Calendario .xlsx senza macro',
            'xlsm' => 'Calendario con macro (.xlsm)',
            'xls' => 'Calendario nel vecchio formato .xls',
            'pdf_ok' => 'Documento PDF generato dal portale',
        ],
    ],
];
