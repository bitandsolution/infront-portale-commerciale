<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\Data\NewUserData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a user (M01 §5). Single entry point for every creation: the first-admin command now,
 * the admin pages with the activation link from PR-04.
 *
 * ⚠️ Audit: the user.created event is written from PR-05, when audit_events exists (D-127).
 */
final class CreateUser
{
    /**
     * Validation rules shared by every creation path (M01 §9: unique and valid e-mail).
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $withPassword): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
        ];

        if ($withPassword) {
            $rules['password'] = ['required', 'string', 'confirmed', Password::defaults()];
        }

        return $rules;
    }

    public function handle(NewUserData $data): User
    {
        $withPassword = $data->password !== null;

        return User::query()->create([
            'first_name' => trim($data->firstName),
            'last_name' => trim($data->lastName),
            'email' => $data->email,
            'phone' => $data->phone,
            'role' => $data->role,
            // Without a chosen password the account waits for the activation link (PR-04):
            // a random hash nobody knows keeps it closed meanwhile.
            'password' => $data->password ?? Hash::make(Str::random(64)),
            'is_active' => true,
            'activated_at' => $withPassword ? now() : null,
        ]);
    }
}
