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

    // Status of an option in the configurator (SellableStatus, F2-05).
    'sellable_statuses' => [
        'available' => 'Vendibile',
        'sold_out' => 'Esaurito',
        'not_managed' => 'Non gestito',
        'not_available' => 'Non disponibile',
    ],

    // Why a club is not selectable in the configurator (ClubSellabilityReason, F2-05).
    'sellability_reasons' => [
        'not_in_season' => 'non è un club Infront attivo iscritto a una competizione della stagione',
        'no_price_list' => 'la stagione non ha un listino',
        'price_list_draft' => 'il listino della stagione è in bozza',
        'no_prices' => 'il listino pubblicato non ha prezzi per il club',
    ],

    // Command inventory:availability (read only, F2-05).
    'console' => [
        'unknown_season' => 'Stagione :season inesistente',
        'unknown_club' => 'Club :club inesistente',
        'selectable' => ':club, stagione :season: selezionabile nel configuratore',
        'not_selectable' => ':club, stagione :season: non selezionabile nel configuratore (:reason)',
        'availability' => 'Disponibilità',
        'options' => 'Opzioni del configuratore',
        'resource' => 'Risorsa',
        'value' => 'Valore',
        'managed' => 'Gestito',
        'capacity' => 'Capacità',
        'committed' => 'Impegnato',
        'remaining' => 'Residuo',
        'kind' => 'Tipo',
        'option' => 'Opzione',
        'status' => 'Stato',
        'list_price' => 'Prezzo di listino',
        'yes' => 'sì',
        'no' => 'no',
        'not_configured' => 'non impostato',
        'free_package' => 'pacchetto senza prezzo',
        'kinds' => [
            'led' => 'Format LED',
            'partnership' => 'Partnership',
            'hospitality' => 'Hospitality',
        ],
    ],

    'errors' => [
        'method_not_supported' => 'Il metodo di calcolo della disponibilità ":method" non è ancora disponibile: va impostato "Stagionale"',
        'season_closed' => 'La stagione :season è chiusa: il magazzino è in sola lettura',
        'club_not_in_grid' => ':club non è un club Infront attivo iscritto a una competizione della stagione :season',
        'step_not_respected' => 'La capacità di :pool va a passi di :step',
        'value_not_managed' => ':value non ha una capacità di magazzino: è disattivato o è il pacchetto Libera',
    ],
];
