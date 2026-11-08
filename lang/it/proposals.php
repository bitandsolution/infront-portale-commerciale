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
    ],

    // Rules of the configurator that refuse an operation (M06 §7): toasts.
    'errors' => [
        'not_agent' => 'Le proposte le crea un agente attivo',
        'client_not_selectable' => 'Scegli uno dei tuoi clienti attivi',
        'season_not_selectable' => 'La stagione scelta è chiusa: scegline un\'altra',
        'not_draft' => 'La proposta n. :number non è più una bozza: non si modifica dal configuratore',
    ],

    // Errors under the fields of step 2 (M06 §8) and dialog of a draft changed elsewhere (D-165).
    'validation' => [
        'conflict' => 'La bozza è stata modificata in un\'altra finestra o su un altro dispositivo. Ricarica la pagina per vedere l\'ultima versione.',
        'starts_on_required' => 'Indica la data di inizio.',
        'starts_on_outside' => 'La data di inizio deve essere nella stagione, dal :from al :to.',
        'ends_on_required' => 'Indica la data di fine.',
        'ends_on_after_season' => 'La data di fine non può superare la fine della stagione (:to).',
        'ends_on_before_start' => 'La data di fine non può essere precedente alla data di inizio.',
    ],

    'attributes' => [
        'client_id' => 'cliente',
        'season_id' => 'stagione',
        'starts_on' => 'data di inizio',
        'ends_on' => 'data di fine',
        'lock_version' => 'versione della bozza',
    ],

    // M07 §4, D-095, D-376.
    'statuses' => [
        'draft' => 'Bozza',
        'submitted' => 'In approvazione',
        'approved' => 'Approvata',
        'rejected' => 'Rifiutata',
        'cancelled' => 'Annullata',
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
    ],
];
