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
    'import_statuses' => [
        'preview' => 'In anteprima',
        'confirmed' => 'Importato',
        'discarded' => 'Scartato',
    ],

    // Fields of the import layout (M02 §6, D-017).
    'import_columns' => [
        'season' => 'Stagione',
        'competition' => 'Competizione',
        'matchday' => 'Giornata',
        'date' => 'Data',
        'time' => 'Ora',
        'home' => 'Squadra casa',
        'away' => 'Squadra ospite',
        'stadium' => 'Stadio',
    ],

    'import' => [
        'matchday_label' => ':numberª giornata',
        'column_option' => 'Colonna :letter · :header',
    ],

    // Reasons why a row of the file is discarded (M02 §8, FL-02 step 2).
    'import_errors' => [
        'other_season' => 'Stagione «:season» diversa da quella scelta',
        'other_competition' => 'Competizione «:competition» diversa da quella scelta',
        'matchday' => 'Giornata mancante o non valida: serve un numero intero da 1 a 99',
        'date_missing' => 'Data mancante',
        'date_invalid' => 'Data non valida',
        'time_invalid' => 'Ora non valida: si scrive come 20:45',
        'club_missing' => 'Squadra mancante',
        'club_unknown' => 'Squadra «:club» da associare a un club',
        'same_club' => 'La squadra di casa e la squadra ospite coincidono',
        'duplicate' => 'Stessa partita della riga :line',
    ],

    // Rows imported anyway, with a note (D-319, D-325, M02 §8).
    'import_warnings' => [
        'unknown_stadium' => 'Stadio «:stadium» sconosciuto: si usa lo stadio abituale della squadra di casa',
        'cancelled' => 'Partita annullata: resta annullata',
    ],

    'import_changes' => [
        'date' => 'data :from → :to',
        'time' => 'ora :from → :to',
        'stadium' => 'stadio diverso',
        'none' => 'non indicata',
    ],

    // Match service (F-12; RB-05, D-187).
    'match_service' => [
        'prorated' => 'Calendario di :club non ancora caricato: :matches partite stimate dal numero standard (:standard) in proporzione al periodo del contratto',
    ],

    // Clubs of the old system (D-318, D-329): command sports:import-legacy-clubs.
    'legacy' => [
        'audit_label' => 'Club dal vecchio sistema',
        'file_unreadable' => 'Il file :path non si legge',
        'file_columns' => 'Il file deve avere la riga di intestazione con le colonne: :columns',
        'unknown_id' => 'L\'id :id indicato in --merge non è nel file',
        'conflicts' => 'Nessun club importato. Codici da sistemare (correggi l\'alias nel file o unisci i club con --merge): :conflicts',
        'code_invalid' => 'id :id «:name» darebbe il codice non valido «:code»',
        'code_taken' => 'id :id «:name» darebbe il codice «:code», già usato',
        'row_skipped' => 'id :id: riga senza id o senza nome, ignorata',
        'color_invalid' => 'id :id: colore «:color» non valido, ignorato',
        'reference_other_club' => 'id :id: il riferimento idoc punta già a un club diverso da :club',
        'alias_taken' => 'Alias «:alias» di :club non aggiunto: indica già :other',
        'top_added' => ':club: flag top match aggiunto per D-257 (nel vecchio sistema non c\'era)',
        'top_dropped' => ':club: nel vecchio sistema aveva il flag top match, che D-257 non prevede',
        'top_missing' => 'Club :club dell\'elenco top match (D-257) non presente nel portale',
        'category_missing' => 'id :id «:name»: categoria 3 inesistente nel vecchio sistema',
        'dry_run' => 'Prova senza salvare: nessuna modifica scritta.',
        'created' => 'Club creati: :count',
        'existing' => 'Club già presenti, non modificati: :count',
        'aliases' => 'Alias aggiunti: :count',
        'references' => 'Riferimenti idoc aggiunti: :count',
        'anomalies' => 'Anomalie da controllare: :count',
        'merge_invalid' => 'Formato di --merge non valido: «:value» (esempio: --merge=21,35)',
    ],

    'console' => [
        'matches_period' => 'Stagione :season, dal :from al :to',
        'matches_club' => 'Club',
        'matches_competition' => 'Competizione',
        'matches_source' => 'Fonte',
        'matches_count' => 'Partite in casa',
        'matches_top' => 'Top match',
        'matches_first' => 'Prima',
        'matches_last' => 'Ultima',
        'matches_calendar' => 'calendario',
        'matches_standard' => 'standard',
        'matches_total' => 'Totale partite: :total',
        'matches_campaign' => 'Campagna: :months mesi (:from → :to)',
        'matches_campaign_none' => 'Campagna: nessuna partita in casa nel periodo',
        'matches_top_total' => 'Top match: :value',
        'matches_top_not_available' => 'n.d. (calendario non caricato)',
        'matches_top_not_applicable' => 'non previsti per queste competizioni',
        'matches_unknown_season' => 'Stagione sconosciuta: :season',
        'matches_unknown_club' => 'Club sconosciuto: :club',
        'matches_invalid_date' => 'Data non valida: :date (formato gg/mm/aaaa)',
        'imports_pruned' => 'Import in anteprima scartati perché fermi da oltre 7 giorni: :count.',
    ],

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
        'file' => 'file del calendario',
        'name' => 'nome',
        'club_id' => 'club',
        'columns' => 'colonne',
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
        'import_columns_saved' => 'Colonne salvate: l\'anteprima è aggiornata',
        'import_name_resolved' => '«:name» associato a :club: dal prossimo import sarà riconosciuto in automatico',
        'import_refreshed' => 'Anteprima ricalcolata',
        'import_confirmed' => 'Calendario importato: :inserted partite inserite, :updated aggiornate, :discarded righe scartate',
        'import_discarded' => 'Import scartato: il calendario non è cambiato',
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
        'calendar_unreadable' => 'Le celle del file non si leggono: aprilo in Excel, salvalo di nuovo come .xlsx e riprova',
        'calendar_empty' => 'Il primo foglio del file non ha partite: servono una riga di intestazione e una riga per ogni partita',
        'calendar_too_many_rows' => 'Il file ha più di :max righe: non sembra il calendario di una competizione',
        'import_closed' => 'Questo import è già concluso: carica di nuovo il file per un nuovo import',
        'import_nothing_to_confirm' => 'Non ci sono partite da importare: correggi le righe in errore o scegli le colonne',
        'import_columns_invalid' => 'Scegli una colonna diversa per ogni campo obbligatorio: giornata, data, squadra casa e squadra ospite',
        'club_not_in_league' => ':club non è iscritto a un campionato della stagione :season: l\'amministratore lo iscrive dalla pagina della stagione',
        'period_outside_season' => 'Il periodo del contratto è fuori dalla stagione :season',
        'import_name_not_unknown' => '«:name» non è tra le squadre da associare di questo import',
        'edition_in_use' => ':edition ha già club iscritti, partite o import: non si può togliere dalla stagione',
    ],
];
