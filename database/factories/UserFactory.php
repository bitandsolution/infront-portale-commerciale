<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

/**
 * Fictitious users only (CLAUDE.md §7, §11). Default: an active agent with the second factor
 * configured (D-136), whose codes come from UserFactory::TOTP_SECRET.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Same hash for every factory user: hashing is slow and no test logs in with it.
     */
    protected static ?string $password;

    /** Fictitious TOTP key (base32) of every factory user: tests compute valid codes from it. */
    public const TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'role' => Role::Agent,
            'password' => static::$password ??= Hash::make(Str::random(32)),
            'is_active' => true,
            'activated_at' => now(),
            'deactivated_at' => null,
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt(self::TOTP_SECRET),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt((string) json_encode(
                array_map(fn (): string => RecoveryCode::generate(), range(1, 8)),
            )),
            'two_factor_confirmed_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * User who has not configured the second factor yet: every page leads to the setup (M01-CA1).
     */
    public function withoutTwoFactor(): static
    {
        return $this->state([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * Known password, for the tests that sign in through the login page.
     */
    public function withPassword(string $password): static
    {
        return $this->state(['password' => Hash::make($password)]);
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }

    public function manager(): static
    {
        return $this->state(['role' => Role::Manager]);
    }

    public function agent(): static
    {
        return $this->state(['role' => Role::Agent]);
    }

    /**
     * Deactivated user (RB-05): the date is required by the users_deactivation_check constraint.
     */
    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
