<?php

/*
| Operations of the portal (PR-09): internal health (/health, health:check). The e-mail alerts of the
| queues are in lang/it/mail.php with the other e-mails.
*/

return [
    'health' => [
        'checks' => [
            'database' => 'Database',
            'redis' => 'Redis',
            'horizon' => 'Code (Horizon)',
            'queues' => 'Attesa nelle code',
            'disk' => 'Disco',
            'scheduler' => 'Scheduler',
        ],
        'line' => '[:status] :check: :detail',
        'status_ok' => 'OK',
        'status_fail' => 'KO',
        'summary_ok' => 'Stato: tutto funziona.',
        'summary_fail' => 'Stato: :failed controlli non superati.',

        'ok' => 'raggiungibile',
        'error' => 'errore :error',
        'horizon_running' => 'in esecuzione',
        'horizon_paused' => 'in pausa',
        'horizon_inactive' => 'non avviato',
        'queue_wait' => ':queue :seconds s',
        'queues_late' => 'attesa oltre :seconds s in :queues (:detail)',
        'disk_used' => 'occupato :used% (limite :limit%)',
        'disk_unknown' => 'spazio non leggibile',
        'scheduler_last' => 'ultimo segnale :seconds s fa',
        'scheduler_never' => 'nessun segnale nelle ultime 24 ore',
    ],
];
