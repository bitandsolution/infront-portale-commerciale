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
        'columns' => [
            'club' => 'Club',
            'format' => 'Format',
            'matches' => 'N. match',
            'minutes' => 'Min./partita',
            'spots' => 'N. spot/partita',
            'price' => 'Proposta economica',
        ],
        'total' => 'Totale / stagione',
        'none' => '—',
        'vat_note' => 'Importi IVA esclusa',
        'discount_percent' => 'Sconto :value%',
        'discount_amount' => 'Sconto :value',
    ],

    'warnings' => [
        'too_heavy' => 'Il PDF pesa :size MB, oltre la soglia di 20 MB (M09 §9).',
    ],

    'errors' => [
        'fonts_not_configured' => 'Font dei documenti non configurati: indicare in DOCUMENT_FONTS_PATH la cartella con i file Interstate (il font sostitutivo è ammesso solo in sviluppo e nei test).',
        'font_missing' => 'File del font non trovato o non leggibile: :path',
        'font_invalid' => 'Il file non è un font TrueType o OpenType valido: :path',
        'sandbox_required' => 'La sandbox di Chromium non si può disattivare nell\'ambiente ":environment": togliere DOCUMENT_CHROMIUM_NO_SANDBOX.',
        'page_count' => 'Il PDF ha :pages pagine invece delle :expected attese.',
        'not_linearized' => 'Il PDF non è linearizzato: qpdf non ha completato l\'ottimizzazione.',
        'font_not_embedded' => 'Il font :font non è incorporato nel PDF come sottoinsieme.',
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
