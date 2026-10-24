<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Scheduled jobs (progettazione-tecnica §5.5.5). In production cron runs "php artisan schedule:run"
| every minute (R2); on the development VM nothing runs by itself: the commands are run by hand.
*/

// Audit retention (RB-A2, D-210, D-211): events older than 5 years, expired IP addresses.
Schedule::command('audit:prune')
    ->dailyAt('03:15')
    ->timezone('Europe/Rome')
    ->withoutOverlapping()
    ->onOneServer();
