<?php

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

/*
| audit:list (D-283): read-only list of the latest events from the server, used for the acceptance
| tests and through SSH until the audit page of slice F8.
|
| The output is read whole with Artisan::output(): one table row holds several of the checked
| values, which expectsOutputToContain would match only once.
*/

/**
 * @param  array<string, string>  $options
 * @return array{int, string}
 */
function auditList(array $options = []): array
{
    $status = Artisan::call('audit:list', $options);

    return [$status, Artisan::output()];
}

it('D-283: lists the latest events, newest first, with user, action, object, changes, reason and IP', function () {
    $admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Bianchi']);
    $agent = User::factory()->agent()->create(['first_name' => 'Luca', 'last_name' => 'Rossi', 'email' => 'luca.rossi@example.test']);

    app(AuditLog::class)->record(AuditAction::UserRoleChanged, $admin, AuditSubject::user($agent), ['role' => 'agent'], ['role' => 'manager']);
    $this->travel(1)->minutes();
    app(AuditLog::class)->access(AuditAction::LoginFailed, null, AuditSubject::attemptedEmail('nessuno@example.test'), 'unknown_email');

    [$status, $output] = auditList();

    expect($status)->toBe(0)
        ->and($output)->toContain('access.login_failed')
        ->toContain('nessuno@example.test')
        ->toContain('E-mail inesistente')
        ->toContain('127.0.0.1')
        ->toContain('user.role_changed')
        ->toContain('Anna Bianchi (admin, #'.$admin->id.')')
        ->toContain('Luca Rossi (luca.rossi@example.test)')
        ->toContain('role: agent → manager')
        ->and(strpos($output, 'access.login_failed'))->toBeLessThan(strpos($output, 'user.role_changed'));

    expect(AuditEvent::query()->count())->toBe(2);
});

it('D-283: filters by area, action and user, and refuses unknown values', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create(['email' => 'luca.rossi@example.test']);
    $other = User::factory()->agent()->create(['email' => 'altro@example.test']);

    app(AuditLog::class)->record(AuditAction::UserDeactivated, $admin, AuditSubject::user($agent));
    app(AuditLog::class)->access(AuditAction::LoginSucceeded, $other);

    expect(auditList(['--area' => 'access'])[1])
        ->toContain('access.login_succeeded')
        ->not->toContain('user.deactivated');

    expect(auditList(['--user' => 'Luca.Rossi@example.test'])[1])
        ->toContain('user.deactivated')
        ->not->toContain('access.login_succeeded');

    expect(auditList(['--action' => 'access.logout'])[1])->toContain('Nessun evento di audit con questi filtri.');

    [$status, $output] = auditList(['--area' => 'proposals']);
    expect($status)->toBe(1)->and($output)->toContain('Area sconosciuta: proposals. Valori ammessi: access, users, settings, sports.');
});
