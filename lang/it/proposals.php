<?php

/*
| Proposals (M06, M07): server texts. The pages of the configurator arrive with F4-02 (texts of the
| pages in lang/it/ui.php, "proposals"); toast texts never end with a period (ToastRegion).
*/

return [
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
