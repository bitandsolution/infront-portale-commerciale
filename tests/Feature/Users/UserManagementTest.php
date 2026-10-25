<?php

use App\Enums\AccessLinkType;
use App\Enums\Role;
use App\Mail\AccessLinkMail;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Users page (M01 §5; D-139): list, new user with activation link, data and role.
*/

beforeEach(function () {
    Mail::fake();
});

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function newUserForm(array $overrides = []): array
{
    return [
        'first_name' => 'Giulia',
        'last_name' => 'Neri',
        'email' => 'giulia.neri@example.test',
        'phone' => '',
        'role' => 'agent',
        ...$overrides,
    ];
}

it('M01-CA3: refuses the Users page and every user function to the manager', function () {
    $manager = User::factory()->manager()->create();
    $agent = User::factory()->agent()->create();

    $this->actingAs($manager);

    $this->get(PortalPages::url('users'))->assertForbidden();
    $this->get(route('users.create'))->assertForbidden();
    $this->post(route('users.store'), newUserForm())->assertForbidden();
    $this->get(route('users.show', $agent))->assertForbidden();
    $this->put(route('users.update', $agent), newUserForm())->assertForbidden();
    $this->post(route('users.deactivate', $agent))->assertForbidden();
    $this->post(route('users.reset-two-factor', $agent))->assertForbidden();
    $this->post(route('users.reset-password', $agent))->assertForbidden();

    expect(User::query()->count())->toBe(2)
        ->and($agent->fresh()?->is_active)->toBeTrue();
    Mail::assertNothingOutgoing();
});

it('M01 §5: lists every user with role, status, second factor and last login', function () {
    $admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Bianchi']);
    User::factory()->agent()->create(['first_name' => 'Luca', 'last_name' => 'Rossi', 'last_login_at' => '2027-03-01 08:30:00']);
    User::factory()->manager()->inactive()->create(['first_name' => 'Marta', 'last_name' => 'Verdi']);
    User::factory()->agent()->withoutTwoFactor()->create(['first_name' => 'Sara', 'last_name' => 'Zeta', 'activated_at' => null]);

    $this->actingAs($admin)
        ->get(PortalPages::url('users'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users', 4)
            ->where('users.0.name', 'Bianchi Anna')
            ->where('users.1.name', 'Rossi Luca')
            ->where('users.1.roleLabel', 'Agente')
            ->where('users.1.status', 'active')
            ->where('users.1.twoFactor', true)
            // 08:30 UTC is 09:30 in Rome (C7)
            ->where('users.1.lastLogin', '01/03/2027 09:30')
            ->where('users.2.status', 'inactive')
            ->where('users.3.status', 'pending')
            ->where('users.3.twoFactor', false)
            ->where('users.3.lastLogin', null)
            ->where('createUrl', route('users.create')));
});

it('M01 §5: creates the user without password and e-mails him the activation link', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('users.store'), newUserForm(['email' => ' Giulia.Neri@Example.test ', 'phone' => '+39 333 000 0000', 'role' => 'manager']))
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'Utente creato: il link di attivazione è stato inviato a giulia.neri@example.test');

    $user = User::query()->where('email', 'giulia.neri@example.test')->sole();

    expect($user->role)->toBe(Role::Manager)
        ->and($user->phone)->toBe('+39 333 000 0000')
        ->and($user->is_active)->toBeTrue()
        ->and($user->activated_at)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    Mail::assertQueued(AccessLinkMail::class, fn (AccessLinkMail $mail): bool => $mail->hasTo('giulia.neri@example.test')
        && $mail->type === AccessLinkType::Activation
        && str_starts_with($mail->url, url('attivazione').'/'));
    Mail::assertQueuedCount(1);
});

it('D-277: never shows the activation link to the admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('users.store'), newUserForm());

    $url = sentAccessLink('giulia.neri@example.test');
    $user = User::query()->where('email', 'giulia.neri@example.test')->sole();

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertDontSee($url, false)
        ->assertDontSee((string) basename((string) parse_url($url, PHP_URL_PATH)), false);
});

it('M01 §9: refuses an e-mail already used, whatever the case, and an unknown role', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->agent()->create(['email' => 'giulia.neri@example.test']);

    $this->actingAs($admin)
        ->post(route('users.store'), newUserForm(['email' => 'GIULIA.NERI@example.test', 'role' => 'superuser', 'first_name' => '']))
        ->assertSessionHasErrors(['email', 'role', 'first_name']);

    expect(User::query()->count())->toBe(2);
    Mail::assertNothingOutgoing();
});

it('M01 §5: shows one user with the actions allowed now', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();
    $pending = User::factory()->agent()->withoutTwoFactor()->create(['activated_at' => null]);
    $inactive = User::factory()->agent()->inactive()->create(['deactivated_by' => $admin->id]);

    $this->actingAs($admin);

    $this->get(route('users.show', $agent))->assertInertia(fn (Assert $page) => $page
        ->component('Users/Show')
        ->where('isSelf', false)
        ->where('actions.deactivate', route('users.deactivate', $agent))
        ->where('actions.reactivate', null)
        ->where('actions.resetPassword', route('users.reset-password', $agent))
        ->where('actions.resetTwoFactor', route('users.reset-two-factor', $agent))
        ->where('actions.resendLink', null));

    $this->get(route('users.show', $pending))->assertInertia(fn (Assert $page) => $page
        ->where('user.status', 'pending')
        ->where('actions.resetPassword', null)
        ->where('actions.resetTwoFactor', null)
        ->where('actions.resendLink', route('users.resend-link', $pending)));

    $this->get(route('users.show', $inactive))->assertInertia(fn (Assert $page) => $page
        ->where('user.status', 'inactive')
        ->where('user.deactivatedBy', $admin->fullName())
        ->where('actions.deactivate', null)
        ->where('actions.reactivate', route('users.reactivate', $inactive))
        ->where('actions.resetPassword', null));

    // D-281: on his own account the admin has no action, only the link to his profile.
    $this->get(route('users.show', $admin))->assertInertia(fn (Assert $page) => $page
        ->where('isSelf', true)
        ->where('actions.deactivate', null)
        ->where('actions.resetPassword', null)
        ->where('actions.resetTwoFactor', null)
        ->where('actions.resendLink', null)
        ->where('profileUrl', route('pages.profile')));
});

it('M01 §5: the admin changes data and role of a user', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create(['email' => 'luca.rossi@example.test']);

    $this->actingAs($admin)
        ->put(route('users.update', $agent), newUserForm([
            'first_name' => 'Luca',
            'last_name' => 'Rossi Bruni',
            'email' => 'luca.rossi@example.test',
            'phone' => '02 1234567',
            'role' => 'manager',
        ]))
        ->assertRedirect()
        ->assertSessionHas('toast.message', "Dati dell'utente salvati");

    $agent->refresh();

    expect($agent->last_name)->toBe('Rossi Bruni')
        ->and($agent->phone)->toBe('02 1234567')
        ->and($agent->role)->toBe(Role::Manager);
    Mail::assertNothingOutgoing();
});

it('D-281: the admin cannot change his own role', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('users.update', $admin), newUserForm(['email' => $admin->email, 'role' => 'manager']))
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'Non puoi eseguire questa operazione sul tuo account: per i tuoi dati usa il profilo.');

    expect($admin->fresh()?->role)->toBe(Role::Admin);
});

it('D-281: a new e-mail of a user still to activate gets a new link, the old one stops working', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('users.store'), newUserForm());
    $oldUrl = sentAccessLink('giulia.neri@example.test');
    $user = User::query()->where('email', 'giulia.neri@example.test')->sole();

    $this->put(route('users.update', $user), newUserForm(['email' => 'g.neri@example.test']))->assertRedirect();

    $newUrl = sentAccessLink('g.neri@example.test', AccessLinkType::Activation);

    $this->post(route('logout'));

    $this->get($oldUrl)->assertInertia(fn (Assert $page) => $page->component('Auth/AccessLinkInvalid'));
    $this->get($newUrl)->assertInertia(fn (Assert $page) => $page->component('Auth/Activate'));
});

it('M01 §9: refuses an e-mail already used by another user when editing', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();
    User::factory()->agent()->create(['email' => 'occupata@example.test']);

    $this->actingAs($admin)
        ->put(route('users.update', $agent), newUserForm(['email' => 'Occupata@example.test']))
        ->assertSessionHasErrors('email');
});
