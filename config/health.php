<?php

/*
| Internal health of the portal (progettazione-tecnica §5.2.7; D-179; PR-09), read by the public
| endpoint /health (200 "ok" or 503 "fail", no details), by the external uptime service every
| minute, by the deploy after the switch of the release (docs/dev/ci.md §2) and, with the details of
| each check, by "php artisan health:check" on the server.
|
| The last backup is not checked yet: the backups of the portal (D-177) do not exist yet. The TLS
| certificate is checked by the external uptime service (the domain is still open, D-180).
*/

return [
    // Queues whose oldest waiting job must be younger than max_queue_wait_seconds (D-220).
    'queues' => ['documents', 'mail', 'default'],

    // A job waiting longer than 10 minutes means the queue is stalled (§5.2.7).
    'max_queue_wait_seconds' => (int) env('HEALTH_MAX_QUEUE_WAIT_SECONDS', 600),

    // Space used on the disk of the application (storage/), in percent (§5.2.7: alarm over 80%).
    'disk_path' => env('HEALTH_DISK_PATH', storage_path()),
    'max_disk_used_percent' => (int) env('HEALTH_MAX_DISK_USED_PERCENT', 80),

    // The scheduler writes a heartbeat every minute (routes/console.php); older than 10 minutes means
    // that cron is not running "php artisan schedule:run" (R2).
    'max_scheduler_silence_seconds' => (int) env('HEALTH_MAX_SCHEDULER_SILENCE_SECONDS', 600),
];
