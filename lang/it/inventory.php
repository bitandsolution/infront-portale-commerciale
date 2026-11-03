<?php

/*
| Inventory (M04): server messages (toasts, errors, validation). The texts of the pages are in
| lang/it/ui.php ("inventory").
*/

return [
    'resources' => [
        'pool' => 'Bacini LED',
        'partnership' => 'Partnership',
        'hospitality' => 'Hospitality',
    ],

    // Names of the fields in the validation messages.
    'attributes' => [
        'season_id' => 'stagione',
        'pools.*.minutes' => 'minuti a partita',
        'pools.*.enabled' => 'gestito',
        'tiers.*.quantity' => 'numero di partnership',
        'tiers.*.enabled' => 'gestito',
        'variants.*.seats' => 'posti',
        'variants.*.enabled' => 'gestito',
    ],

    'validation' => [
        'minutes_format' => 'Scrivi i minuti a partita, per esempio 6,5 per 6′30″',
        'minutes_step' => 'I minuti vanno a passi di :step',
        'minutes_max' => 'Al massimo :max a partita',
    ],

    // Toasts end without a full stop: ToastRegion adds it (ToastTextsTest).
    'toasts' => [
        'capacities_saved' => 'Capacità di :club salvate',
        'capacities_saved_below' => 'Capacità di :club salvate: :resources restano sotto l\'impegnato',
        'nothing_changed' => 'Nessuna modifica da salvare',
    ],

    'confirm_below' => 'Queste capacità restano sotto l\'impegnato: :resources. Il salvataggio è consentito e il residuo negativo resta evidenziato',
    'below_item' => ':name (impegnato :committed, capacità :capacity)',
    'below_item_not_managed' => ':name (non gestito, impegnato :committed)',

    'errors' => [
        'method_not_supported' => 'Il metodo di calcolo della disponibilità ":method" non è ancora disponibile: va impostato "Stagionale"',
        'season_closed' => 'La stagione :season è chiusa: il magazzino è in sola lettura',
        'club_not_in_grid' => ':club non è un club Infront attivo iscritto a una competizione della stagione :season',
        'step_not_respected' => 'La capacità di :pool va a passi di :step',
        'value_not_managed' => ':value non ha una capacità di magazzino: è disattivato o è il pacchetto Libera',
    ],
];
