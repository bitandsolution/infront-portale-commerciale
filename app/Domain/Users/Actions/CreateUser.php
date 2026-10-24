<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\Data\NewUserData;
use App\Domain\Users\Services\SendAccessLink;
use App\Enums\AccessLinkType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a user (M01 §5). Single entry point for every creation:
 * - the first-admin command, with the password typed on the server (D-270);
 * - the admin from the Users page (D-139), without password: the user receives the activation
 *   link (48 hours) and sets password and second factor himself (D-277, D-278).
 *
 * The e-mail is sent inside the transaction: if it cannot be sent, no user is left half created.
 *
 * ⚠️ Audit: the user.created event is written from PR-05 (D-127), with $createdBy as author.
 */
final class CreateUser
{
    public function __construct(private readonly SendAccessLink $sendAccessLink) {}

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

    public function handle(NewUserData $data, ?User $createdBy = null): User
    {
        return DB::transaction(function () use ($data): User {
            $withPassword = $data->password !== null;

            $user = User::query()->create([
                'first_name' => trim($data->firstName),
                'last_name' => trim($data->lastName),
                'email' => $data->email,
                'phone' => self::phone($data->phone),
                'role' => $data->role,
                // Without a chosen password the account waits for the activation link:
                // a random hash nobody knows keeps it closed meanwhile.
                'password' => $data->password ?? Hash::make(Str::random(64)),
                'is_active' => true,
                'activated_at' => $withPassword ? now() : null,
            ]);

            if (! $withPassword) {
                ($this->sendAccessLink)($user, AccessLinkType::Activation);
            }

            return $user;
        });
    }

    /**
     * Optional phone: an empty value is stored as null.
     */
    public static function phone(?string $phone): ?string
    {
        $phone = $phone !== null ? trim($phone) : null;

        return $phone === '' ? null : $phone;
    }
}
