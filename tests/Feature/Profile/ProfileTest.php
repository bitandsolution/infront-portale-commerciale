<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Personal profile (M01 §5; D-267): every role, from the user menu of the top bar; name, surname,
| phone and change of the password, which closes the other sessions (D-273).
*/

const PROFILE_PASSWORD = 'Password-Profilo-2027!';
const PROFILE_NEW_PASSWORD = 'Nuova-Password-Profilo-2027!';

beforeEach(function () {
    fakePasswordArchive();
});

it('D-267: every role opens his profile, linked from the user menu', function (Role $role) {
    $user = User::factory()->create(['role' => $role, 'first_name' => 'Laura', 'phone' => '333 1234567']);

    $this->actingAs($user)
        ->get(PortalPages::url('profile'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('profile.firstName', 'Laura')
            ->where('profile.email', $user->email)
            ->where('profile.phone', '333 1234567')
            ->where('profile.role', $role->label())
            ->where('layout.user.profileUrl', PortalPages::url('profile')));
})->with(Role::cases());

it('M01 §5: the user changes name, surname and phone, not his e-mail or role', function () {
    $agent = User::factory()->agent()->create(['email' => 'luca@example.test']);

    $this->actingAs($agent)
        ->put(route('profile.update'), [
            'first_name' => ' Luca ',
            'last_name' => 'Rossi',
            'phone' => '',
            'email' => 'altro@example.test',
            'role' => 'admin',
        ])
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'Dati personali salvati');

    $agent->refresh();
    expect($agent->first_name)->toBe('Luca')
        ->and($agent->last_name)->toBe('Rossi')
        ->and($agent->phone)->toBeNull()
        ->and($agent->email)->toBe('luca@example.test')
        ->and($agent->role)->toBe(Role::Agent);
});

it('D-273: changes the password with the current one; this session stays open', function () {
    $user = User::factory()->manager()->withPassword(PROFILE_PASSWORD)->create();

    $this->actingAs($user)
        ->put(route('profile.password'), [
            'current_password' => PROFILE_PASSWORD,
            'password' => PROFILE_NEW_PASSWORD,
            'password_confirmation' => PROFILE_NEW_PASSWORD,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', 'Password aggiornata: le altre sessioni aperte sono state chiuse');

    expect(Hash::check(PROFILE_NEW_PASSWORD, (string) $user->fresh()?->password))->toBeTrue();
    $this->get(PortalPages::url('dashboard'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

it('D-273: the change from the profile closes the other sessions of the user', function () {
    $user = User::factory()->agent()->withPassword(PROFILE_PASSWORD)->create();

    // Session on the tablet, opened before the change.
    $this->actingAs($user)->get(PortalPages::url('dashboard'))->assertOk();
    $tablet = session()->all();

    // Change from the desktop.
    $this->put(route('profile.password'), [
        'current_password' => PROFILE_PASSWORD,
        'password' => PROFILE_NEW_PASSWORD,
        'password_confirmation' => PROFILE_NEW_PASSWORD,
    ])->assertSessionHasNoErrors();

    // Back on the tablet.
    session()->flush();
    session()->put($tablet);
    $this->get(PortalPages::url('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('D-214: refuses a wrong current password and a weak new one', function () {
    $user = User::factory()->agent()->withPassword(PROFILE_PASSWORD)->create();
    $hash = $user->password;

    $this->actingAs($user)
        ->put(route('profile.password'), [
            'current_password' => 'Sbagliata-2027!',
            'password' => 'corta',
            'password_confirmation' => 'diversa',
        ])
        ->assertSessionHasErrors([
            'current_password' => 'La password attuale non è corretta.',
            'password',
        ]);

    expect($user->fresh()?->password)->toBe($hash);
});
