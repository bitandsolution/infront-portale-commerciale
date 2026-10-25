<?php

/*
| E-mails of the portal (M01 §7; PR-08): template, access links, lock after failed attempts, register
| of the e-mails (notification_logs) and its commands. The e-mails of the proposals arrive with F5.
*/

return [
    // Template (resources/views/vendor/mail): text placeholder of the Infront logo until the official
    // logo arrives (no remote image).
    'logo' => 'INFRONT',
    'portal' => 'Portale Commerciale',
    'footer' => 'Messaggio automatico del Portale Commerciale Infront: non rispondere a questa e-mail.',
    'button_fallback' => 'Se il pulsante non funziona, copia e incolla questo indirizzo nel browser:',

    'greeting' => 'Ciao :name,',
    'datetime' => ':date alle :time',

    // Access links (D-277…D-280): 48 hours, once.
    'access_link' => [
        'expiry' => 'Il link vale :hours ore e si può usare una sola volta.',

        'activation' => [
            'subject' => 'Attiva il tuo account del Portale Commerciale Infront',
            'intro' => "L'amministratore ti ha creato un account nel Portale Commerciale Infront. Per attivarlo scegli la password e configura la verifica in due passaggi con l'app di autenticazione dello smartphone.",
            'action' => "Attiva l'account",
            'ignore' => "Se non ti aspettavi questo messaggio, ignoralo e avvisa l'amministratore del portale.",
        ],
        'two_factor_reset' => [
            'subject' => 'Configura di nuovo il tuo accesso al Portale Commerciale Infront',
            'intro' => "L'amministratore ha azzerato la verifica in due passaggi del tuo account. Per entrare di nuovo scegli una nuova password e configura l'app di autenticazione dello smartphone.",
            'action' => "Configura l'accesso",
            'ignore' => "Se non ti aspettavi questo messaggio, ignoralo e avvisa l'amministratore del portale.",
        ],
        'password_reset' => [
            'subject' => 'Scegli una nuova password per il Portale Commerciale Infront',
            'intro' => "L'amministratore ha azzerato la password del tuo account. Scegline una nuova e confermala con il codice dell'app di autenticazione.",
            'action' => 'Scegli la nuova password',
            'ignore' => "Se non ti aspettavi questo messaggio, avvisa l'amministratore del portale.",
        ],
        'password_forgotten' => [
            'subject' => 'Nuova password per il Portale Commerciale Infront',
            'intro' => "Hai chiesto di scegliere una nuova password dalla pagina di accesso. Sceglila dal link qui sotto e confermala con il codice dell'app di autenticazione.",
            'action' => 'Scegli la nuova password',
            'ignore' => "Se non l'hai chiesto tu, ignora questo messaggio: la tua password attuale continua a funzionare. Se ricevi altre e-mail come questa, avvisa l'amministratore del portale.",
        ],
    ],

    // Lock after 5 failed attempts (M01 §7, D-274): to the user and to the admins.
    'login_locked' => [
        'subject' => 'Accesso al Portale Commerciale Infront bloccato per :minutes minuti',
        'intro' => 'il :time sono stati registrati 5 tentativi di accesso non riusciti con il tuo indirizzo e-mail. Per sicurezza l\'accesso da quella rete è bloccato per :minutes minuti.',
        'yours' => 'Se sei stato tu, attendi e riprova. Se non ricordi la password, usa "Password dimenticata?" nella pagina di accesso.',
        'not_yours' => "Se non sei stato tu, avvisa subito l'amministratore del portale.",
        'action' => 'Vai alla pagina di accesso',
    ],
    'login_locked_admin' => [
        'subject' => 'Accesso bloccato per :name',
        'intro' => "il :time l'accesso di un utente è stato bloccato per :minutes minuti dopo 5 tentativi non riusciti.",
        'user' => 'Utente',
        'email' => 'E-mail',
        'role' => 'Ruolo',
        'ip' => 'Indirizzo IP',
        'unlock' => "Il blocco termina da solo. L'utente ha ricevuto un'e-mail con lo stesso avviso; gli eventi sono nell'audit.",
        'action' => "Apri la scheda dell'utente",
    ],

    // Operations alerts to the admins (progettazione-tecnica §5.2.7, D-298; PR-09): sent at once,
    // outside the queue, at most one an hour for the same kind of job or the same queue.
    'job_failed' => [
        'subject' => 'Lavoro non riuscito nella coda :queue',
        'intro' => 'il :time un lavoro della coda ":queue" non è riuscito nemmeno all\'ultimo dei tentativi previsti.',
        'job' => 'Lavoro',
        'queue' => 'Coda',
        'error' => 'Errore',
        'note' => 'Nel pannello delle code trovi i dettagli e il pulsante per riprovare il lavoro. Per un\'ora non riceverai altri avvisi per lo stesso tipo di lavoro.',
        'action' => 'Apri i lavori non riusciti',
    ],
    'queue_stalled' => [
        'subject' => 'Coda :queue ferma da oltre 10 minuti',
        'intro' => 'il :time il pannello delle code ha stimato un\'attesa di circa :minutes minuti sulla coda ":queue": i lavori in coda, come le e-mail e i documenti, arrivano in ritardo.',
        'note' => 'Controlla nel pannello che i processi delle code siano attivi. Per un\'ora non riceverai altri avvisi per questa coda.',
        'action' => 'Apri il pannello delle code',
    ],

    // Register of the e-mails (notification_logs, D-209).
    'types' => [
        'activation' => 'Attivazione',
        'two_factor_reset' => 'Nuova configurazione dell\'accesso',
        'password_reset' => 'Nuova password (dall\'admin)',
        'password_forgotten' => 'Password dimenticata',
        'login_locked' => 'Accesso bloccato (utente)',
        'login_locked_admin' => 'Accesso bloccato (admin)',
        'job_failed' => 'Lavoro non riuscito (admin)',
        'queue_stalled' => 'Coda ferma (admin)',
    ],
    'statuses' => [
        'queued' => 'In coda',
        'sent' => 'Inviata',
        'failed' => 'Non inviata',
    ],

    'list' => [
        'empty' => 'Nessuna e-mail nel registro.',
        'columns' => ['Data (Roma)', 'Tipo', 'Destinatario', 'Stato', 'Tentativi', 'Errore'],
        'unknown_status' => 'Stato sconosciuto ":status". Valori ammessi: :statuses.',
    ],
    'prune' => [
        'done' => 'Registro delle e-mail: :deleted righe più vecchie di :months mesi cancellate.',
    ],
];
