<?php

/*
| System settings (D-191, D-286; PR-07): labels and descriptions of the keys of
| App\Enums\SettingKey, shared by the Settings page and by the audit events (object label).
| The texts of the page itself (title, buttons) are in lang/it/ui.php, "settings".
*/

return [
    'groups' => [
        'inventory' => 'Magazzino e configuratore',
        'audit' => 'Audit',
    ],

    'keys' => [
        'availability_method' => [
            'label' => 'Metodo di calcolo della disponibilità',
            'help' => 'Con il metodo stagionale un impegno vale per tutta la stagione, qualunque sia il periodo del contratto.',
            'fixed' => 'Il calcolo «per partita» non è ancora disponibile: il valore non si modifica.',
        ],
        'led_step_seconds' => [
            'label' => 'Passo dei minuti LED',
            'help' => 'I minuti LED si scelgono a passi di 30 secondi (0,5′).',
            'fixed' => 'Il passo non si modifica: un valore diverso renderebbe non validi i minuti già salvati.',
        ],
        'hospitality_max_seats' => [
            'label' => 'Massimo posti hospitality per club',
            'help' => 'Posti Premium e Base insieme, per ogni club di una proposta.',
            'fixed' => '',
        ],
        'forced_reason_min_length' => [
            'label' => 'Lunghezza minima della motivazione di forzatura',
            'help' => "Caratteri richiesti quando l'admin approva una proposta oltre la disponibilità.",
            'fixed' => '',
        ],
        'audit_ip_retention_days' => [
            'label' => "Conservazione dell'indirizzo IP degli accessi",
            'help' => "Dopo questo periodo l'IP degli eventi di accesso viene svuotato; l'evento resta nell'audit. Il massimo coincide con i 5 anni di conservazione degli eventi.",
            'fixed' => '',
        ],
    ],

    'units' => [
        'seconds' => 'secondi',
        'seats' => 'posti',
        'characters' => 'caratteri',
        'days' => 'giorni',
    ],

    'availability_methods' => [
        'seasonal' => 'Stagionale',
        'per_match' => 'Per partita',
    ],

    'last_change' => 'Ultima modifica il :date da :user',

    'toasts' => [
        'saved' => 'Impostazioni salvate',
        'unchanged' => 'Nessuna modifica da salvare',
    ],
];
