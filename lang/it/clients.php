<?php

/*
| Clients (M05): server texts (toasts, names of the fields in the validation messages). The texts of
| the pages are in lang/it/ui.php ("clients"). Toast texts never end with a period (ToastRegion).
*/

return [
    'toasts' => [
        'sector_created' => 'Settore :sector creato',
        'sector_updated' => 'Settore :sector salvato',
        'nothing_changed' => 'Nessuna modifica da salvare',
        'client_created' => 'Cliente :client creato',
        'client_updated' => 'Cliente :client salvato',
        'client_reassigned' => 'Cliente :client assegnato a :owner',
        'client_deactivated' => 'Cliente :client disattivato',
        'client_reactivated' => 'Cliente :client riattivato',
        'clients_merged' => 'Cliente :client unito a :keep',
    ],

    // Duplicate (M05 FL-02, ux-architettura §4.5): the agent never learns whose client it is.
    'errors' => [
        'duplicate' => 'Cliente già presente in anagrafica: contatta l\'amministratore',
        'duplicate_admin' => 'Cliente già presente in anagrafica: :client, dell\'agente :owner',
        'owner_not_agent' => 'Il cliente deve appartenere a un agente attivo',
        'sector_not_active' => 'Il settore :sector non è attivo: scegline un altro',
        'merged' => 'Il cliente :client è stato unito a un altro cliente: non si modifica più',
        'already_owner' => 'Il cliente :client è già di :owner',
        'already_inactive' => 'Il cliente :client è già non attivo',
        'already_active' => 'Il cliente :client è già attivo',
        'merge_into_itself' => 'Scegli un cliente diverso da quello da unire',
        'merge_target_not_active' => 'Il cliente :client non è attivo: il cliente che resta deve essere attivo',
    ],

    'validation' => [
        'vat_number_format' => 'La P.IVA è di 11 cifre (si può scrivere anche con il prefisso IT).',
        'vat_number_check' => 'La P.IVA non è valida: controlla le cifre.',
    ],

    'attributes' => [
        'name' => 'nome',
        'sort_order' => 'ordine',
        'is_active' => 'attivo',
        'company_name' => 'ragione sociale',
        'vat_number' => 'P.IVA',
        'email' => 'e-mail',
        'commodity_sector_id' => 'settore merceologico',
        'sector_free_text' => 'descrizione del settore',
        'contact_name' => 'referente',
        'logo' => 'logo',
        'owner_id' => 'agente',
        'keep_id' => 'cliente che resta',
    ],
];
