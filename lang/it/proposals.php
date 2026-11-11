<?php

/*
| Proposals (M06, M07): server texts (audit label, toasts, errors). The texts of the pages of the
| configurator are in lang/it/ui.php ("proposals"); toast texts never end with a period (ToastRegion).
*/

return [
    // Object of the audit events (AuditSubject::proposal) and title of a draft.
    'label' => 'Proposta n. :number',

    'toasts' => [
        'draft_deleted' => 'Bozza n. :number eliminata',
        'client_created' => 'Cliente :client creato e scelto per la proposta',
        'clubs_copied' => 'Configurazione copiata su :count club',
        'clubs_unchanged' => 'Nessuna modifica: i club scelti hanno già questa configurazione',
        'duplicated' => 'Proposta n. :number creata come copia della n. :original',
        'variant_added' => 'Variante :letter creata (proposta n. :number)',
        'variant_deleted' => 'Variante :letter (proposta n. :number) eliminata',
    ],

    // Rules of the configurator that refuse an operation (M06 §7): toasts.
    'errors' => [
        'not_agent' => 'Le proposte le crea un agente attivo',
        'client_not_selectable' => 'Scegli uno dei tuoi clienti attivi',
        'season_not_selectable' => 'La stagione scelta è chiusa: scegline un\'altra',
        'not_draft' => 'La proposta n. :number non è più una bozza: non si modifica dal configuratore',
        'nothing_to_copy' => 'Scegli il club da copiare e almeno un altro club della proposta',
        'not_duplicable' => 'La proposta n. :number non si può duplicare',
        'too_many_variants' => 'Una proposta può avere al massimo :max varianti',
        'variants_share_header' => 'Le varianti di una proposta hanno lo stesso cliente e la stessa stagione: per cambiarli elimina prima le altre varianti',
    ],

    // Errors under the fields of step 2 (M06 §8) and dialog of a draft changed elsewhere (D-165).
    'validation' => [
        'conflict' => 'La bozza è stata modificata in un\'altra finestra o su un altro dispositivo. Ricarica la pagina per vedere l\'ultima versione.',
        'season_required' => 'Scegli la stagione.',
        'starts_on_required' => 'Indica la data di inizio.',
        'starts_on_outside' => 'La data di inizio deve essere nella stagione, dal :from al :to.',
        'ends_on_required' => 'Indica la data di fine.',
        'ends_on_after_season' => 'La data di fine non può superare la fine della stagione (:to).',
        'ends_on_before_start' => 'La data di fine non può essere precedente alla data di inizio.',
    ],

    // Errors of the clubs of a draft (DraftEvaluator, SaveDraft; M06 §5, §8, §9; D-373): under the
    // fields of step 4, with the period.
    'club_errors' => [
        'no_season' => 'Scegli la stagione al passo 2.',
        'not_selectable' => ':club non è selezionabile nella stagione :season: :reason. Rimuovi il club dalla proposta.',
        'not_priced' => 'Prezzo non calcolabile: :reason.',
        'tier_sold_out' => 'Il pacchetto :name è esaurito: scegline un altro.',
        'tier_not_available' => 'Il pacchetto :name non è più in vendita per questo club: scegline un altro.',
        'format_sold_out' => 'Il format :name è esaurito: scegline un altro o togli la visibilità LED.',
        'format_not_available' => 'Il format :name non è più in vendita per questo club: scegline un altro o togli la visibilità LED.',
        'led_step' => 'I minuti vanno a passi di :step e devono essere almeno :step.',
        'led_over_residual' => 'I minuti vanno a passi di :step e non possono superare il residuo (:remaining).',
        'no_matches' => 'Nessuna partita in casa nel periodo: togli la visibilità LED o cambia il periodo.',
        'variant_sold_out' => 'L\'hospitality :name è esaurita: togli i posti.',
        'variant_not_available' => 'L\'hospitality :name non è più in vendita per questo club: togli i posti.',
        'seats_over_residual' => 'Posti :variant oltre il residuo (:remaining).',
        'seats_max' => 'Massimo :max posti per club.',
        'no_elements' => 'Configura almeno un elemento (un pacchetto diverso da Libera, la visibilità LED o l\'hospitality) oppure rimuovi il club.',
        'unknown' => 'Configurazione dei club non valida: ricarica la pagina.',
    ],

    'attributes' => [
        'client_id' => 'cliente',
        'season_id' => 'stagione',
        'starts_on' => 'data di inizio',
        'ends_on' => 'data di fine',
        'lock_version' => 'versione della bozza',
        'clubs' => 'club',
        'source_club_id' => 'club da copiare',
        'target_club_ids' => 'club su cui copiare',
    ],

    // M07 §4, D-095, D-376.
    'statuses' => [
        'draft' => 'Bozza',
        'submitted' => 'In approvazione',
        'approved' => 'Approvata',
        'rejected' => 'Rifiutata',
        'cancelled' => 'Annullata',
    ],

    // Notes of the history (F4-05).
    'history' => [
        'duplicated_from' => 'Copia della proposta n. :number',
        'variant_from' => 'Variante :letter, copia della proposta n. :number',
    ],

    // History of the proposal (M07 F-10, D-199).
    'event_types' => [
        'created' => 'Creata',
        'submitted' => 'Inviata in approvazione',
        'withdrawn' => 'Ritirata',
        'edited_in_review' => 'Modificata in approvazione',
        'discount_set' => 'Sconto applicato',
        'approved' => 'Approvata',
        'forced' => 'Approvata con forzatura',
        'rejected' => 'Rifiutata',
        'resubmitted' => 'Reinviata in approvazione',
        'new_version' => 'Nuova versione',
        'cancelled' => 'Annullata',
        'duplicated' => 'Duplicata',
        'variant_added' => 'Variante aggiunta',
    ],
];
