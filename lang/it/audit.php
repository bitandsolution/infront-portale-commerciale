<?php

/*
| Audit (M10 §4): names of areas, actions and reasons, texts of the server commands audit:list and
| audit:prune. The audit page of the portal (slice F8) uses the same names.
*/

return [
    'system' => 'Sistema (server)',

    'areas' => [
        'access' => 'Accessi',
        'users' => 'Utenti',
        'settings' => 'Impostazioni',
        'sports' => 'Anagrafiche sportive',
    ],

    'actions' => [
        'access_login_succeeded' => 'Login riuscito',
        'access_login_failed' => 'Login fallito',
        'access_logout' => 'Logout',
        'access_locked_out' => 'Account bloccato',
        'access_two_factor_failed' => 'Codice di verifica errato',
        'access_recovery_code_used' => 'Accesso con codice di recupero',
        'access_session_expired' => 'Sessione scaduta',
        'access_session_closed' => 'Sessione chiusa dal portale',
        'access_password_reset_requested' => 'Password dimenticata: richiesta del link',
        'user_created' => 'Utente creato',
        'user_updated' => 'Utente modificato',
        'user_role_changed' => 'Ruolo modificato',
        'user_deactivated' => 'Utente disattivato',
        'user_reactivated' => 'Utente riattivato',
        'user_access_link_sent' => 'Link di accesso inviato',
        'user_password_reset_requested' => 'Reset password avviato',
        'user_password_reset' => 'Nuova password dal link',
        'user_password_changed' => 'Password cambiata',
        'user_profile_updated' => 'Profilo modificato',
        'user_two_factor_reset' => 'Verifica in due passaggi azzerata',
        'user_two_factor_configured' => 'Verifica in due passaggi configurata',
        'user_activated' => 'Account attivato',
        'setting_updated' => 'Impostazione modificata',
        'season_created' => 'Stagione creata',
        'season_updated' => 'Stagione modificata',
        'competition_created' => 'Competizione creata',
        'competition_updated' => 'Competizione modificata',
        'edition_created' => 'Competizione aggiunta alla stagione',
        'edition_updated' => 'Numero standard di partite modificato',
        'edition_removed' => 'Competizione tolta dalla stagione',
    ],

    'reasons' => [
        'unknown_email' => 'E-mail inesistente',
        'invalid_credentials' => 'Password errata',
        'not_activated' => 'Account non ancora attivato',
        'deactivated' => 'Utente disattivato',
        'locked' => 'Account bloccato',
        'idle' => 'Inattività',
        'two_factor_reset' => 'Verifica in due passaggi azzerata',
        'two_factor_not_configured' => 'Verifica in due passaggi non configurata',
        'throttled' => 'Troppe richieste',
    ],

    'list' => [
        'empty' => 'Nessun evento di audit con questi filtri.',
        'unknown_area' => 'Area sconosciuta: :area. Valori ammessi: :areas.',
        'unknown_action' => 'Azione sconosciuta: :action.',
        'columns' => [
            'occurred_at' => 'Data e ora',
            'user' => 'Utente',
            'action' => 'Azione',
            'object' => 'Oggetto',
            'changes' => 'Prima → dopo',
            'reason' => 'Motivo',
            'ip' => 'IP',
            'request' => 'Codice',
        ],
    ],

    'prune' => [
        'done' => "Conservazione dell'audit: :deleted eventi oltre 5 anni eliminati, IP svuotato su :ip_cleared eventi oltre :days giorni.",
    ],
];
