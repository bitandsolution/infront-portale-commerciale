<?php

/*
| Generation of the documents (M09; fetta S1). Errors of the tools of the pipeline, for the operator
| (logs, commands, the document marked "failed" from F7).
*/

return [
    'errors' => [
        'fonts_not_configured' => 'Font dei documenti non configurati: indicare in DOCUMENT_FONTS_PATH la cartella con i file Interstate (il font sostitutivo è ammesso solo in sviluppo e nei test).',
        'font_missing' => 'File del font non trovato o non leggibile: :path',
        'font_invalid' => 'Il file non è un font TrueType o OpenType valido: :path',
        'sandbox_required' => 'La sandbox di Chromium non si può disattivare nell\'ambiente ":environment": togliere DOCUMENT_CHROMIUM_NO_SANDBOX.',
    ],
];
