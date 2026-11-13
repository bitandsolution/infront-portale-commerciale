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
        'group_submitted' => 'Proposta n. :number inviata in approvazione',
        'group_rejected' => 'Proposta n. :number rifiutata',
        'group_approved' => 'Proposta n. :number approvata',
        'client_chosen' => 'Scelta del cliente registrata: variante :letter (proposta n. :number)',
        // DM-07 (D-399).
        'custom_slide_deleted' => 'Slide personalizzata «:title» eliminata',
    ],

    // Slides composed with blocks (D-399, D-428…D-432; DM-07): default title, errors under the fields
    // of the editor, warnings of the content.
    'custom_slides' => [
        'default_title' => 'Slide personalizzata :number',
        'validation' => [
            'title_required' => 'Dai un titolo alla slide: è il nome con cui compare nell\'elenco.',
            'too_many_blocks' => 'Una slide ha al massimo :max blocchi.',
            'too_many_images' => 'Una slide ha al massimo :max immagini, compresa quella dello schema "Immagine a sinistra".',
            'table_rows' => 'La tabella ha al massimo :max righe.',
            'table_columns' => 'La tabella ha al massimo :max colonne.',
            'image_unknown' => 'Immagine non trovata: caricala di nuovo.',
            'image_required' => 'Scegli un\'immagine JPEG o PNG.',
        ],
        'overflow' => 'Il contenuto non entra nella slide: nel PDF viene ridotto al 70% e la parte che non entra viene tagliata con "…".',
        'reduced' => 'Contenuto ridotto al :percent% per entrare nella slide.',
        'no_background' => 'Lo sfondo della slide personalizzata non è ancora nella libreria: la slide entra nel documento quando Infront lo aggiunge.',
    ],

    // Rules of the configurator that refuse an operation (M06 §7): toasts.
    'errors' => [
        'not_agent' => 'Le proposte le crea un agente attivo',
        'client_not_selectable' => 'Scegli uno dei tuoi clienti attivi',
        'season_not_selectable' => 'La stagione scelta è chiusa: scegline un\'altra',
        'not_draft' => 'La proposta n. :number non è una bozza né una proposta rifiutata: non si modifica dal configuratore',
        'nothing_to_copy' => 'Scegli il club da copiare e almeno un altro club della proposta',
        'not_duplicable' => 'La proposta n. :number non si può duplicare',
        'too_many_variants' => 'Una proposta può avere al massimo :max varianti',
        'group_not_ready' => 'Prima dell\'invio correggi la proposta: varianti con club in errore o senza elementi: :variants',
        'group_not_submittable' => 'La proposta non si può inviare: è già in approvazione o è stata decisa',
        'group_not_submitted' => 'La proposta non è più in approvazione: ricarica la pagina',
        // DM-03b: approval (D-216, D-409) and choice of the client (D-410).
        'approval_blocked' => 'Approvazione bloccata: :problems',
        'discount_out_of_range' => 'Lo sconto va da 0 a 100% o da 0 al totale di listino',
        'group_not_approved' => 'La scelta del cliente si registra solo su una proposta approvata con almeno due varianti, una volta sola',
        'variant_not_in_group' => 'La variante scelta non appartiene a questa proposta',
        'variants_share_header' => 'Le varianti di una proposta hanno lo stesso cliente e la stessa stagione: per cambiarli elimina prima le altre varianti',
        // DM-05: slides of the document (D-397, D-419).
        'document_not_editable' => 'Le slide del documento si scelgono finché la proposta è in bozza o, per chi approva, in approvazione: ricarica la pagina',
        // DM-07 (D-399, D-430).
        'too_many_custom_slides' => 'Una proposta ha al massimo :max slide personalizzate',
        'too_many_custom_images' => 'Una slide personalizzata ha al massimo :max immagini',
        'custom_image_target' => 'L\'immagine non ha più il suo blocco nella slide: ricarica la pagina',
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
        'not_chosen' => 'Non scelta',
    ],

    // Notes of the history (F4-05).
    'history' => [
        'duplicated_from' => 'Copia della proposta n. :number',
        'variant_from' => 'Variante :letter, copia della proposta n. :number',
    ],

    // History of the proposal (M07 F-10, D-199).
    // DM-03b: problems that block the approval (D-216, D-409), in the toast and in the detail.
    'approval_problems' => [
        'period' => 'variante :letter senza periodo',
        'variant' => 'variante :letter: :problem',
        'club' => ':club: :message',
        'no_clubs' => 'nessun club nella proposta',
        'shortfall' => ':club, :resource: richiesti :requested, disponibili :remaining',
        'shortfall_variants' => ':club, :resource: richiesti :requested dalle varianti :letters, disponibili :remaining',
        'contract' => '1 contratto',
        'contracts' => ':count contratti',
        'seat' => '1 posto',
        'seats' => ':count posti',
    ],

    // DM-03b: notes of the history.
    'event_notes' => [
        'discount_percent' => 'Sconto :value% (€ :amount): totale finale € :final',
        'discount_amount' => 'Sconto € :amount (:percent%): totale finale € :final',
        'chosen' => 'Il cliente ha scelto questa variante',
        'not_chosen' => 'Il cliente ha scelto la variante :letter',
    ],

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
        'client_chosen' => 'Scelta del cliente registrata',
        'new_version' => 'Nuova versione',
        'cancelled' => 'Annullata',
        'duplicated' => 'Duplicata',
        'variant_added' => 'Variante aggiunta',
    ],
];
