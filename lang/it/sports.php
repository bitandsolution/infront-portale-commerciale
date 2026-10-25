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

    'matchday_stages' => [
        'regular' => 'Fase regolare',
        'playoff' => 'Playoff',
        'playout' => 'Playout',
    ],

    'match_statuses' => [
        'scheduled' => 'In programma',
        'cancelled' => 'Annullata',
    ],

    'alias_sources' => [
        'manual' => 'Inserito a mano',
        'import' => 'Da import calendario',
        'legacy' => 'Dal vecchio sistema',
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
        'city' => 'città',
        'capacity' => 'capienza',
        'home_stadium_id' => 'stadio abituale',
        'primary_color' => 'colore principale',
        'secondary_color' => 'colore secondario',
        'is_infront' => 'club Infront',
        'is_top_match_opponent' => 'avversario da top match',
        'logo' => 'logo',
        'alias' => 'alias',
        'club_ids' => 'club',
        'club_ids.*' => 'club',
        'competition_season_id' => 'competizione',
        'stage' => 'fase',
        'matchday' => 'giornata',
        'match_date' => 'data',
        'kickoff_time' => 'ora',
        'home_club_id' => 'squadra di casa',
        'away_club_id' => 'squadra ospite',
        'stadium_id' => 'stadio',
    ],

    'validation' => [
        'season_code_years' => 'Il codice deve indicare due anni consecutivi, per esempio 2027-28.',
        'color' => 'Il colore va scritto come #RRGGBB, per esempio #C8102E.',
    ],

    'toasts' => [
        'season_created' => 'Stagione :season creata. Ora aggiungi le competizioni con il numero standard di partite',
        'season_updated' => 'Stagione :season salvata',
        'competition_created' => 'Competizione :competition creata',
        'competition_updated' => 'Competizione :competition salvata',
        'edition_created' => ':edition aggiunta alla stagione',
        'edition_updated' => 'Numero standard di :edition salvato',
        'edition_removed' => ':edition tolta dalla stagione',
        'stadium_created' => 'Stadio :stadium creato',
        'stadium_updated' => 'Stadio :stadium salvato',
        'club_created' => 'Club :club creato. Ora puoi caricare il logo e aggiungere gli alias',
        'club_updated' => 'Club :club salvato',
        'club_logo_changed' => 'Logo di :club aggiornato',
        'alias_created' => 'Alias «:alias» aggiunto',
        'alias_removed' => 'Alias «:alias» tolto',
        'clubs_enrolled' => 'Club iscritti a :edition: :count',
        'enrollment_removed' => ':club tolto da :edition',
        'match_created' => 'Partita :match inserita',
        'match_updated' => 'Partita :match salvata',
        'match_cancelled' => 'Partita :match annullata',
        'match_restored' => 'Partita :match ripristinata',
        'warning_suffix' => 'Attenzione: :warnings',
        'nothing_changed' => 'Nessuna modifica da salvare',
    ],

    'warnings' => [
        'same_day' => ':club gioca già un\'altra partita il :date in questa competizione',
    ],

    'errors' => [
        'season_overlaps' => 'Le date si sovrappongono alla stagione :season: due stagioni non possono coprire gli stessi giorni',
        'season_closed' => 'La stagione :season è chiusa: le sue anagrafiche si consultano soltanto. Per modificarle riapri la stagione',
        'edition_exists' => ':edition è già nella stagione',
        'alias_taken' => '«:alias» identifica già :club: lo stesso nome non può indicare due club',
        'alias_empty' => "L'alias deve contenere almeno una lettera o una cifra",
        'club_in_another_league' => ':club è già iscritto a :competition in questa stagione: un club gioca un solo campionato per stagione',
        'club_already_enrolled' => ':club è già iscritto a questa competizione',
        'enrollment_has_matches' => ':club ha già partite in questa competizione: annullale o correggile prima di toglierlo',
        'club_not_enrolled' => ':club non è iscritto a :edition: iscrivilo prima dalla pagina della stagione',
        'date_outside_season' => 'La data è fuori dalla stagione :season',
        'match_exists' => 'Esiste già una partita con la stessa giornata, squadra di casa e squadra ospite',
        'edition_in_use' => ':edition ha già club iscritti, partite o import: non si può togliere dalla stagione',
    ],
];
