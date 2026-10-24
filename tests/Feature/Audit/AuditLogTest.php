<?php

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\SupportCode;
use Illuminate\Http\Request;

/*
| Writing of the audit events (M10 §4.2; D-127, D-283): author with name and role copied, area,
| action, object, values before and after, reason, IP only for the accesses, request code.
*/

it('M10 §4.2: records author, role, area, action, object, values and request code', function () {
    $admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Bianchi']);
    $agent = User::factory()->agent()->create(['first_name' => 'Luca', 'last_name' => 'Rossi', 'email' => 'luca.rossi@example.test']);

    $this->travelTo(now()->setTime(10, 15, 30));
    $event = app(AuditLog::class)->record(
        AuditAction::UserRoleChanged,
        $admin,
        AuditSubject::user($agent),
        ['role' => 'agent'],
        ['role' => 'manager'],
    )->fresh();

    expect($event?->occurred_at->toDateTimeString())->toBe(now()->toDateTimeString());
    expect($event)
        ->user_id->toBe($admin->id)
        ->user_name->toBe('Anna Bianchi')
        ->user_role->toBe('admin')
        ->area->toBe('users')
        ->action->toBe('user.role_changed')
        ->object_type->toBe('user')
        ->object_id->toBe($agent->id)
        ->object_label->toBe('Luca Rossi (luca.rossi@example.test)')
        ->old_values->toBe(['role' => 'agent'])
        ->new_values->toBe(['role' => 'manager'])
        ->ip_address->toBeNull()
        ->request_id->toBe(app(SupportCode::class)->value());
});

it('M10 §8: keeps name and role of the author as they were, after later changes', function () {
    $admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Bianchi']);
    $event = app(AuditLog::class)->record(AuditAction::UserUpdated, $admin);

    $admin->forceFill(['first_name' => 'Annalisa', 'role' => Role::Manager])->save();

    expect($event->fresh())->user_name->toBe('Anna Bianchi')->user_role->toBe('admin');
});

it('D-283: records the system as author of the server commands', function () {
    $user = User::factory()->create();

    $event = app(AuditLog::class)->record(AuditAction::UserTwoFactorReset, null, AuditSubject::user($user));

    expect($event->fresh())
        ->user_id->toBeNull()
        ->user_name->toBe(AuditEvent::SYSTEM)
        ->user_role->toBe(AuditEvent::SYSTEM);
});

it('M10 §4.2: records the IP address only for the access events', function () {
    $user = User::factory()->create();

    app()->instance('request', Request::create('/', 'GET', server: ['REMOTE_ADDR' => '203.0.113.7']));

    $access = app(AuditLog::class)->access(AuditAction::LoginSucceeded, $user);
    $other = app(AuditLog::class)->record(AuditAction::UserUpdated, $user, AuditSubject::user($user));

    expect($access->fresh()?->ip_address)->toBe('203.0.113.7')
        ->and($other->fresh()?->ip_address)->toBeNull();
});

it('D-283: never records passwords, hashes, tokens or codes of the second factor, even if passed', function () {
    $user = User::factory()->create();

    $event = app(AuditLog::class)->record(AuditAction::UserUpdated, $user, AuditSubject::user($user), [
        'password' => 'vecchia',
        'two_factor_secret' => 'SEGRETO',
        'first_name' => 'Mario',
    ], [
        'password' => 'nuova',
        'two_factor_recovery_codes' => '["a"]',
        'remember_token' => 'tok',
        'token' => 'link',
        'code' => '123456',
        'first_name' => 'Marco',
    ])->fresh();

    expect($event?->old_values)->toBe(['first_name' => 'Mario'])
        ->and($event?->new_values)->toBe(['first_name' => 'Marco']);
});

it('D-283: keeps only the fields that changed', function () {
    [$old, $new] = AuditLog::changes(
        ['first_name' => 'Mario', 'phone' => null, 'is_active' => true],
        ['first_name' => 'Mario', 'phone' => '333', 'is_active' => false],
    );

    expect($old)->toBe(['phone' => null, 'is_active' => true])
        ->and($new)->toBe(['phone' => '333', 'is_active' => false]);
});
