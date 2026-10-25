<?php

/*
| Sports registry (M02): labels of the values of the system, server messages (toasts, refusals,
| validation). The texts of the pages are in lang/it/ui.php ("sports").
*/

return [
    'season_statuses' => [
        'planned' => 'In preparazione',
        'open' => 'Aperta',
        'closed' => 'Chiusa',
    ],

    'competition_types' => [
        'league' => 'Campionato',
        'cup' => 'Coppa',
    ],

    // Names of the fields in the validation messages (the generic ones of lang/it/validation.php
    // would call "code" the code of the second factor).
    'attributes' => [
        'code' => 'codice',
        'name' => 'nome',
        'short_name' => 'nome breve',
        'starts_on' => 'data di inizio',
        'ends_on' => 'data di fine',
        'status' => 'stato',
        'type' => 'tipo',
        'tier' => 'livello',
        'has_top_match_kpi' => 'KPI top match',
        'is_active' => 'attiva',
        'sort_order' => 'ordine',
        'standard_home_matches' => 'numero standard di partite in casa',
        'competition_id' => 'competizione',
        'season_id' => 'stagione',
    ],

    'validation' => [
        'season_code_years' => 'Il codice deve indicare due anni consecutivi, per esempio 2027-28.',
    ],

    'toasts' => [
        'season_created' => 'Stagione :season creata. Ora aggiungi le competizioni con il numero standard di partite.',
        'season_updated' => 'Stagione :season salvata.',
        'competition_created' => 'Competizione :competition creata.',
        'competition_updated' => 'Competizione :competition salvata.',
        'edition_created' => ':edition aggiunta alla stagione.',
        'edition_updated' => 'Numero standard di :edition salvato.',
        'edition_removed' => ':edition tolta dalla stagione.',
        'nothing_changed' => 'Nessuna modifica da salvare.',
    ],

    'errors' => [
        'season_overlaps' => 'Le date si sovrappongono alla stagione :season: due stagioni non possono coprire gli stessi giorni.',
        'season_closed' => 'La stagione :season è chiusa: le sue anagrafiche si consultano soltanto. Per modificarle riapri la stagione.',
        'edition_exists' => ':edition è già nella stagione.',
        'edition_in_use' => ':edition ha già club iscritti, partite o import: non si può togliere dalla stagione.',
    ],
];
