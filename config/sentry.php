<?php

use App\Domain\Operations\Services\SentryScrubber;

/*
| Errors to Sentry (progettazione-tecnica §5.2.7; D-179; PR-09): data region EU, no personal data.
|
| - Off while SENTRY_LARAVEL_DSN is empty: development, CI and until the project is created. The DSN
|   comes from a Sentry organization with data stored in the EU (address "…ingest.de.sentry.io").
| - Backend errors only; no performance tracing, no profiling, no logs sent to Sentry.
| - send_default_pii off and App\Domain\Operations\Services\SentryScrubber as last step: only the id
|   and the role of the user, the support code (D-265), the page without query string, messages
|   without e-mail or IP addresses nor SQL. The privacy options are fixed here, not in .env.
*/

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'release' => env('SENTRY_RELEASE'),

    // Empty: the Laravel environment (APP_ENV).
    'environment' => env('SENTRY_ENVIRONMENT'),

    'sample_rate' => 1.0,

    'traces_sample_rate' => null,

    'profiles_sample_rate' => null,

    'send_default_pii' => false,

    'before_send' => [SentryScrubber::class, 'beforeSend'],

    'ignore_transactions' => ['/health'],

    'breadcrumbs' => [
        // Log messages and cache keys may carry data of the users: not collected.
        'logs' => false,
        'cache' => false,
        'livewire' => false,
        // SQL statements without their values.
        'sql_queries' => true,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        // Addresses of outside services may carry tokens: not collected.
        'http_client_requests' => false,
        'notifications' => false,
    ],

    // Tracing is off (traces_sample_rate null): these only keep the SDK from collecting SQL values.
    'tracing' => [
        'sql_bindings' => false,
    ],
];
