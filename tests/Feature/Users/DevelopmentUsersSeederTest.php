<?php

use App\Models\User;
use Database\Seeders\DevelopmentUsersSeeder;
use Illuminate\Support\Facades\Hash;

/*
| Fictitious users of the local machine (D-270), with the optional known password and shared
| TOTP key of the .env of the VM (PR-03, docs/dev/ambiente-locale.md).
*/

const SEEDER_TOTP = 'KRUGS4ZANFZSA5DFON2CAZLZEBQWE3DE';

it('PR-03: gives the test users the password and the shared key of the .env', function () {
    config([
        'auth.development_users.password' => 'Password-locale-2027',
        'auth.development_users.totp_secret' => SEEDER_TOTP,
    ]);

    $this->seed(DevelopmentUsersSeeder::class);

    $users = User::query()->orderBy('email')->get()->keyBy('email');
    expect($users)->toHaveCount(5);

    foreach ($users as $email => $user) {
        expect(Hash::check('Password-locale-2027', $user->password))->toBeTrue("password of {$email}");
    }

    foreach (['gestore1@example.test', 'gestore2@example.test', 'agente1@example.test', 'agente3@example.test'] as $email) {
        expect($users[$email]->two_factor_confirmed_at)->not->toBeNull()
            ->and(decrypt((string) $users[$email]->two_factor_secret))->toBe(SEEDER_TOTP)
            ->and($users[$email]->recoveryCodes())->toHaveCount(8);
    }

    // agente2 tries the mandatory setup (M01-CA1).
    expect($users['agente2@example.test']->two_factor_secret)->toBeNull()
        ->and($users['agente2@example.test']->two_factor_confirmed_at)->toBeNull()
        ->and($users['agente3@example.test']->is_active)->toBeFalse();
});

it('PR-03: signs a test user in with the shared key', function () {
    config([
        'auth.development_users.password' => 'Password-locale-2027',
        'auth.development_users.totp_secret' => SEEDER_TOTP,
    ]);
    $this->seed(DevelopmentUsersSeeder::class);

    $this->post(route('login.store'), ['email' => 'agente1@example.test', 'password' => 'Password-locale-2027'])
        ->assertRedirect(route('two-factor.login'));
    $this->post(route('two-factor.login.store'), ['code' => totpCode(SEEDER_TOTP)])
        ->assertRedirect(route('pages.dashboard'));

    $this->assertAuthenticated();
});

it('PR-03: without the variables keeps random passwords and no second factor', function () {
    config([
        'auth.development_users.password' => null,
        'auth.development_users.totp_secret' => null,
    ]);

    $this->seed(DevelopmentUsersSeeder::class);

    expect(User::query()->whereNotNull('two_factor_secret')->count())->toBe(0)
        ->and(User::query()->count())->toBe(5);
});

it('PR-03: refuses a short password or a key that is not base32', function (string $password, string $secret) {
    config([
        'auth.development_users.password' => $password,
        'auth.development_users.totp_secret' => $secret,
    ]);

    $this->seed(DevelopmentUsersSeeder::class);
})->with([
    'password corta' => ['corta', SEEDER_TOTP],
    'chiave non valida' => ['Password-locale-2027', 'chiave-non-valida-0189'],
])->throws(RuntimeException::class);
