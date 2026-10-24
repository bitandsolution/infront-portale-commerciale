<?php

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;

/*
| Retention of the audit trail (RB-A2, M10-CA7; D-128, D-210, D-211, D-283): audit:prune deletes the
| events older than 5 years and clears the IP address after config('audit.ip_retention_days'),
| every night.
*/

/**
 * Access event written by the portal at a moment in the past.
 */
function auditEventAt(User $user, DateTimeInterface $moment, AuditAction $action = AuditAction::LoginSucceeded): AuditEvent
{
    test()->travelTo($moment);
    $event = app(AuditLog::class)->access($action, $user);
    test()->travelBack();

    return $event;
}

it('M10-CA7: deletes the events older than 5 years, the others stay', function () {
    $user = User::factory()->create();
    $old = auditEventAt($user, now()->subYears(5)->subDays(2));
    $older = auditEventAt($user, now()->subYears(7));
    $recent = auditEventAt($user, now()->subYears(5)->addDays(2));
    $today = app(AuditLog::class)->record(AuditAction::UserUpdated, null, AuditSubject::user($user));

    $this->artisan('audit:prune')
        ->expectsOutputToContain('2 eventi oltre 5 anni eliminati')
        ->assertSuccessful();

    expect(AuditEvent::query()->orderBy('id')->pluck('id')->all())->toBe([$recent->id, $today->id])
        ->and(AuditEvent::query()->whereKey([$old->id, $older->id])->exists())->toBeFalse();
});

it('D-211: clears the IP address after the configured days, the event staying', function () {
    config(['audit.ip_retention_days' => 30]);
    $user = User::factory()->create();
    $expired = auditEventAt($user, now()->subDays(31));
    $kept = auditEventAt($user, now()->subDays(29));

    $this->artisan('audit:prune')
        ->expectsOutputToContain('IP svuotato su 1 eventi oltre 30 giorni')
        ->assertSuccessful();

    expect($expired->fresh())->ip_address->toBeNull()->user_id->toBe($user->id)->action->toBe('access.login_succeeded')
        ->and($kept->fresh()?->ip_address)->toBe('127.0.0.1');
});

it('D-211: by default keeps the IP address for 5 years, as the events', function () {
    expect(config('audit.ip_retention_days'))->toBe(1826);

    $user = User::factory()->create();
    $event = auditEventAt($user, now()->subYears(4));

    $this->artisan('audit:prune')->assertSuccessful();

    expect($event->fresh()?->ip_address)->toBe('127.0.0.1');
});

it('M10-CA7: runs every night at 3:15, Rome time, without overlapping', function () {
    /** @var ScheduledEvent|null $task */
    $task = collect(app(Schedule::class)->events())
        ->first(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'audit:prune'));

    expect($task)->not->toBeNull()
        ->and($task?->expression)->toBe('15 3 * * *')
        ->and($task?->timezone)->toBe('Europe/Rome')
        ->and($task?->withoutOverlapping)->toBeTrue();
});
