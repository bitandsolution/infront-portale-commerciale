<?php

use App\Enums\Ability;
use App\Http\Middleware\EnsureTwoFactorIsConfigured;
use Illuminate\Support\Str;

/*
| Queues and workers (progettazione-tecnica §5.5.1; D-220, D-222; PR-08).
|
| Three queues on Redis, one supervisor each, with a fixed number of processes: "balance" off and
| minProcesses = maxProcesses (with "balance" off Horizon still scales between the two by the size
| of the queue, so an idle queue would keep only minProcesses workers):
|
|   queue       processes  tries  waits between tries      timeout
|   documents   2 (D-222)  3      1, 5, 15 minutes         300 s
|   mail        2          5      10 s … 5 minutes         60 s
|   default     1          3      —                        120 s
|
| Tries and timeout here apply to every job of the queue; a job class may set its own (the e-mails
| do it in App\Mail\PortalMail, the documents of slice F7 in their job). The waits between tries are
| set by the job classes (backoff()), so each kind of job keeps its own.
|
| The "retry_after" of the redis connection (config/queue.php) must stay above the longest timeout,
| otherwise a slow document would be picked up twice.
|
| Panel: /horizon, admin only (Ability::QueuesMonitor), with the same rules as the portal pages:
| signed in, active, second factor configured (App\Providers\HorizonServiceProvider). It has no menu
| entry (ux-architettura): the address is in docs/dev/ambiente-locale.md.
*/

$supervisors = [
    'supervisor-documents' => [
        'connection' => 'redis',
        'queue' => ['documents'],
        'balance' => 'off',
        'minProcesses' => 2,
        'maxProcesses' => 2,
        'maxTime' => 0,
        'maxJobs' => 0,
        'memory' => 512,
        'tries' => 3,
        'timeout' => 300,
        'nice' => 0,
    ],
    'supervisor-mail' => [
        'connection' => 'redis',
        'queue' => ['mail'],
        'balance' => 'off',
        'minProcesses' => 2,
        'maxProcesses' => 2,
        'maxTime' => 0,
        'maxJobs' => 0,
        'memory' => 128,
        'tries' => 5,
        'timeout' => 60,
        'nice' => 0,
    ],
    'supervisor-default' => [
        'connection' => 'redis',
        'queue' => ['default'],
        'balance' => 'off',
        'minProcesses' => 1,
        'maxProcesses' => 1,
        'maxTime' => 0,
        'maxJobs' => 0,
        'memory' => 128,
        'tries' => 3,
        'timeout' => 120,
        'nice' => 0,
    ],
];

return [

    'name' => env('HORIZON_NAME'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    // Redis connection of config/database.php used by Horizon for its own data.
    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug((string) env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    // Same rules as the portal pages (routes/web.php): guests to the login, deactivated users signed
    // out, users without the second factor to its setup, everybody but the admin 403 (D-215).
    'middleware' => [
        'web',
        'auth',
        'auth.session',
        EnsureTwoFactorIsConfigured::class,
        'can:'.Ability::QueuesMonitor->value,
    ],

    // Seconds of estimated wait after which a queue counts as "long wait": 10 minutes for every queue,
    // the threshold of progettazione-tecnica §5.2.7. Horizon then raises LongWaitDetected and the
    // active admins receive an e-mail, at most one an hour per queue (App\Listeners\SendQueueStalledAlert,
    // PR-09, D-298).
    'waits' => [
        'redis:documents' => 600,
        'redis:mail' => 600,
        'redis:default' => 600,
    ],

    // Minutes the panel keeps the jobs. Failed jobs: 7 days, like Horizon's default.
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],

    'silenced_tags' => [],

    // Snapshots taken every 5 minutes by horizon:snapshot (routes/console.php): 24 hours of charts.
    'metrics' => [
        'trim_snapshots' => [
            'job' => 288,
            'queue' => 288,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 128,

    'defaults' => $supervisors,

    // The same supervisors in every environment: on the VM, staging and production the queues
    // behave alike (one worker command: "php artisan horizon").
    'environments' => [
        'production' => $supervisors,
        'staging' => $supervisors,
        'local' => $supervisors,
    ],

    // "php artisan horizon:listen" (development only) restarts the workers when these files change.
    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'resources/**/*.php',
        'routes',
        'lang',
        'composer.lock',
        '.env',
    ],
];
