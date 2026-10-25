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

// Files of the private disk without a stored_files row, older than 24 hours (C10, PR-06), e.g. left
// by a process killed between the write and the commit.
Schedule::command('files:prune-orphans')
    ->dailyAt('03:45')
    ->timezone('Europe/Rome')
    ->withoutOverlapping()
    ->onOneServer();

// Register of the e-mails (D-209): rows older than 12 months, every Sunday.
Schedule::command('notifications:prune')
    ->weeklyOn(0, '04:00')
    ->timezone('Europe/Rome')
    ->withoutOverlapping()
    ->onOneServer();

// Charts of the Horizon panel (PR-08): a snapshot of the queue metrics every 5 minutes.
Schedule::command('horizon:snapshot')->everyFiveMinutes();
