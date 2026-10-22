<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fictitious users only (CLAUDE.md §7, §11). Default: an active agent.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Same hash for every factory user: hashing is slow and no test logs in with it.
     */
    protected static ?string $password;

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
            'remember_token' => Str::random(10),
        ];
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
