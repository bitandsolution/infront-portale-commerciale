<?php

/*
| Catalogue and price lists (M03): labels of the values of the system, server messages (toasts,
| validation). The texts of the pages are in lang/it/ui.php ("catalog").
*/

return [
    'value_kinds' => [
        'led_format' => 'Format LED',
        'partnership_tier' => 'Livello di partnership',
        'hospitality_variant' => 'Variante hospitality',
        'benefit_category' => 'Categoria di benefit',
    ],

    'notes' => [
        'pools' => 'Consuma: :pools',
        'free_package' => 'Pacchetto Libera: senza prezzo, senza benefit e senza contatore',
    ],

    // Names of the fields in the validation messages.
    'attributes' => [
        'name' => 'nome',
        'description' => 'descrizione',
        'sort_order' => 'ordine',
        'is_active' => 'attivo',
    ],

    'toasts' => [
        'value_updated' => 'Valore :value salvato',
        'nothing_changed' => 'Nessuna modifica da salvare',
    ],
];
