<?php

/*
| Generation of the documents (M09; fetta S1). Texts written in the PDF and errors of the pipeline
| for the operator (logs, commands, the document marked "failed" from F7).
|
| The fixed texts of the layouts move to the table layout_texts with slice F6 (D-124, D-202): here
| they serve the proof of slice S1.
*/

return [
    'network_recap' => [
        // Column titles of the original recap (D-314).
        'columns' => [
            'club' => 'CLUB',
            'format' => 'Format',
            'matches' => 'No di Match',
            'minutes' => 'Min. / Partita',
            'spots' => 'N.Spot / game',
            'price' => 'PROPOSTA ECONOMICA',
        ],
        'total' => 'Totale / stagione',
        'none' => '—',
        'vat_note' => 'Importi IVA esclusa',
        'discount_percent' => 'Sconto :value%',
        'discount_amount' => 'Sconto :value',
    ],

    // DM-04: recaps of the documents of the groups (D-396, D-415).
    'recap' => [
        'variant_title' => 'Proposta :letter',
        'partnership' => ':tier Partner',
        'led' => 'LED',
        'hospitality' => 'Hospitality :variant · :seats posti',
        'hospitality_one' => 'Hospitality :variant · 1 posto',
    ],

    // DM-04: the document of a group (audit, PDF metadata, statuses).
    'label' => 'Documento della proposta n. :number',
    'metadata' => [
        'title' => 'Infront Proposta – :client',
        'subject' => 'Stagione :season',
    ],
    'statuses' => [
        'queued' => 'In coda',
        'generating' => 'In preparazione',
        'ready' => 'Pronto',
        'failed' => 'Non riuscito',
    ],

    // DM-04: why a document could not be generated (shown in the detail of the group, D-416).
    'failures' => [
        'no_approved_variants' => 'Il gruppo non ha varianti approvate: nessun documento da generare.',
        'missing_slides' => ':missing nella libreria della stagione :season: aggiungila in "Contenuti › Slide" e rigenera il documento.',
        'missing_slides_many' => ':missing nella libreria della stagione :season: aggiungile in "Contenuti › Slide" e rigenera il documento.',
        'slide_without_image' => 'La slide ":slide" non ha l\'immagine di sfondo.',
        'file_missing' => 'Il file dell\'immagine della slide ":file" non si trova sul disco.',
        'generation' => 'Generazione non riuscita dopo l\'ultimo tentativo: :error',
    ],

    'warnings' => [
        'too_heavy' => 'Il PDF pesa :size MB, oltre la soglia di 20 MB (M09 §9).',
        'fallback_font' => 'Nel PDF compare il font :font: qualche carattere non esiste nel font del documento ed è stato sostituito.',
    ],

    'errors' => [
        'group_not_approved' => 'Il documento si genera solo per un gruppo con almeno una variante approvata',
        'fonts_not_configured' => 'Font dei documenti non configurati: indicare in DOCUMENT_FONTS_PATH la cartella con i file Interstate (il font sostitutivo è ammesso solo in sviluppo e nei test).',
        'font_missing' => 'File del font non trovato o non leggibile: :path',
        'font_invalid' => 'Il file non è un font TrueType o OpenType valido: :path',
        'sandbox_required' => 'La sandbox di Chromium non si può disattivare nell\'ambiente ":environment": togliere DOCUMENT_CHROMIUM_NO_SANDBOX.',
        'page_count' => 'Il PDF ha :pages pagine invece delle :expected attese.',
        'not_linearized' => 'Il PDF non è linearizzato: qpdf non ha completato l\'ottimizzazione.',
        'font_not_embedded' => 'Il font :font non è incorporato nel PDF come sottoinsieme.',
    ],

    'toasts' => [
        'regenerating' => 'Documento in preparazione: sarà pronto tra poco',
    ],

    'console' => [
        'try_only_local' => 'Il comando esiste solo negli ambienti di sviluppo e di test.',
        'try_intro' => 'Prova della pipeline PDF (fetta S1): :pages pagine, font :fonts, immagini :images.',
        'try_fonts_interstate' => 'Interstate (DOCUMENT_FONTS_PATH)',
        'try_fonts_substitute' => 'DejaVu, sostitutivo (DOCUMENT_FONTS_PATH vuoto)',
        'try_images_generated' => 'generate (GD)',
        'try_images_samples' => 'reali da :path',
        'try_samples_missing' => 'DOCUMENT_SAMPLES_PATH non è impostato o non contiene immagini JPEG o PNG 1920×1080.',
        'try_columns' => [
            'measure' => 'Misura',
            'value' => 'Valore',
        ],
        'try_measures' => [
            'compose' => 'Composizione e immagini',
            'render' => 'Chromium',
            'optimize' => 'qpdf',
            'check' => 'Controlli',
            'total' => 'Totale',
            'pages' => 'Pagine',
            'size' => 'Peso',
            'fonts' => 'Font incorporati',
            'linearized' => 'Linearizzato',
        ],
        'try_ms' => ':value ms',
        'try_mb' => ':value MB',
        'try_yes' => 'sì',
        'try_ca4_ok' => 'M09-CA4: :size MB, entro i 15 MB.',
        'try_ca4_ko' => 'M09-CA4: :size MB, oltre i 15 MB.',
        'try_pdf' => 'PDF scaricabile da admin: :url',
        'try_previews' => 'Anteprime PNG e PDF nella cartella: :path',
        'try_warning' => 'Avviso: :warning',
    ],
];
