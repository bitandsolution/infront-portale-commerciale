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
    ],
];
