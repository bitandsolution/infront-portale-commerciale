<?php

namespace App\Domain\Users\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Tokens of the access links (activation and resets), in the password_reset_tokens table
 * (D-209, D-277; progettazione-tecnica §5.3.6).
 *
 * - One token per user: the table key is the e-mail, so a new link invalidates the previous one.
 * - Valid for 48 hours and once: the Action that uses it deletes it in the same transaction.
 * - Only a keyed hash (HMAC-SHA256 with the application key) is stored: the table alone does not
 *   open any account, and the user is found from the token, so the link carries no e-mail.
 */
final class AccessLinks
{
    public const LIFETIME_HOURS = 48;

    private const TABLE = 'password_reset_tokens';

    /**
     * New token for the user, replacing any previous one. Returns the clear token for the link.
     */
    public function create(User $user): string
    {
        $token = Str::random(64);

        $this->deleteFor($user->email);

        DB::table(self::TABLE)->insert([
            'email' => $user->email,
            'token' => $this->hash($token),
            'created_at' => now(),
        ]);

        return $token;
    }

    /**
     * Active user of a valid token (not expired), or null.
     */
    public function userFor(string $token): ?User
    {
        $email = DB::table(self::TABLE)
            ->where('token', $this->hash($token))
            ->where('created_at', '>', now()->subHours(self::LIFETIME_HOURS))
            ->value('email');

        if (! is_string($email)) {
            return null;
        }

        return User::query()->where('email', $email)->where('is_active', true)->first();
    }

    public function delete(User $user): void
    {
        $this->deleteFor($user->email);
    }

    /**
     * Also used when the admin changes the e-mail of a user: the old address loses its link.
     */
    public function deleteFor(string $email): void
    {
        DB::table(self::TABLE)->where('email', $email)->delete();
    }

    private function hash(string $token): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new LogicException('APP_KEY is required to sign the access links.');
        }

        return hash_hmac('sha256', $token, $key);
    }
}
