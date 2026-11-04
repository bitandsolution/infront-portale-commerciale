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

    // Price list of a season (F2-02, D-333).
    'price_list_statuses' => [
        'draft' => 'Bozza',
        'published' => 'Pubblicato',
    ],
    'price_list_label' => 'Listino :season',
    'on_top_general' => 'Generale',

    // Names of the fields in the validation messages.
    'attributes' => [
        'name' => 'nome',
        'description' => 'descrizione',
        'sort_order' => 'ordine',
        'is_active' => 'attivo',
        'season_id' => 'stagione',
        'club_id' => 'club',
        'led_format_id' => 'format',
        'unit_price' => 'costo per 1′ a partita',
        'column_id' => 'valore del catalogo',
        'amount' => 'importo',
        'partnership_tier_id' => 'livello di partnership',
        'texts' => 'testi dei benefit',
        'texts.*' => 'testo del benefit',
        'title' => 'titolo',
        'body' => 'testo',
    ],

    'validation' => [
        'amount_format' => 'Scrivi un importo in euro con al massimo 2 decimali, con la virgola: per esempio 3.157,89',
        'amount_max' => 'L\'importo è troppo alto',
    ],

    // Toasts end without a full stop: ToastRegion adds it (ToastTextsTest).
    'toasts' => [
        'value_updated' => 'Valore :value salvato',
        'nothing_changed' => 'Nessuna modifica da salvare',
        'price_list_created' => 'Listino :season creato in bozza',
        'price_list_published' => 'Listino :season pubblicato: da ora è usato dal configuratore',
        'price_created' => 'Prezzo di :club, :value salvato',
        'price_updated' => 'Prezzo di :club, :value aggiornato',
        'price_removed' => ':club, :value: ora non disponibile',
        'price_unchanged' => 'Prezzo di :club, :value invariato',
        'benefits_saved' => 'Benefit di :club, :tier salvati',
        'on_top_created' => 'Testo on top :title aggiunto',
        'on_top_updated' => 'Testo on top :title salvato',
        'on_top_removed' => 'Testo on top :title tolto',
    ],

    'errors' => [
        'season_closed' => 'La stagione :season è chiusa: il suo listino è in sola lettura',
        'price_list_exists' => 'Il listino della stagione :season esiste già',
        'already_published' => 'Il listino :season è già pubblicato: la pubblicazione non si annulla',
        'club_not_in_grid' => ':club non è un club Infront attivo iscritto a una competizione della stagione :season',
        'not_sold_led' => 'Il format :value non è attivo: riattivalo da Catalogo, Valori',
        'not_sold_partnership' => 'Il livello :value non è attivo: riattivalo da Catalogo, Valori',
        'not_sold_hospitality' => 'La variante :value non è attiva: riattivala da Catalogo, Valori',
        'free_package' => 'Il pacchetto :value non ha prezzo né benefit',
        'price_list_not_published' => 'Il listino della stagione :season non è pubblicato: i prezzi delle proposte usano solo il listino pubblicato',
        'price_not_available' => ':value non è disponibile per :club nel listino :season',
        'led_step' => 'I minuti a partita di :club vanno a passi di :step',
        'too_many_seats' => 'Massimo :max posti hospitality per club (:club)',
        'invalid_selection' => 'Scelta non valida: un format LED con i suoi minuti per club, posti hospitality da 1 in su, un club una sola volta',
    ],

    // Command catalog:price (read only, F2-06).
    'console' => [
        'unknown_season' => 'Stagione :season inesistente',
        'unknown_club' => 'Club :club inesistente',
        'unknown_value' => ':value non esiste nel catalogo',
        'invalid_minutes' => 'Minuti non validi: :minutes (scrivi per esempio 1,5 per 1′30″)',
        'invalid_seats' => 'Posti non validi: :seats (scrivi VARIANTE:POSTI, per esempio PREMIUM:2)',
        'invalid_date' => 'Data non valida: :date (scrivi gg/mm/aaaa)',
        'nothing' => 'Indica almeno un format LED con --format e --minutes, un livello con --tier o dei posti con --seats',
        'period' => 'Listino :season · periodo dal :from al :to',
        'club' => 'Club',
        'matches' => 'Partite',
        'source' => 'Fonte',
        'calendar' => 'calendario',
        'standard' => 'numero standard',
        'led' => 'LED',
        'partnership' => 'Partnership',
        'hospitality' => 'Hospitality',
        'total' => 'Totale club',
        'list_total' => 'Totale di listino: :total €',
        'led_line' => ':format :minutes × :unit € × :matches partite = :price €',
        'free_package' => ':tier (senza prezzo)',
        'seat_line' => ':variant :seats × :price €',
    ],
];
