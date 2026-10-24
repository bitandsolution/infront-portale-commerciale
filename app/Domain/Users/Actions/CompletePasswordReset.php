<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\Services\AccessLinks;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * New password from the reset link sent by the admin (M01 §5; D-279): the user confirms it with
 * the code of his authenticator app (the second factor does not change). The new hash closes
 * every other session (auth.session, D-273); the token is deleted, so the link works once.
 *
 * ⚠️ Audit: user.password_reset from PR-05 (D-127).
 */
final class CompletePasswordReset
{
    public function __construct(
        private readonly AccessLinks $links,
        private readonly TwoFactorAuthenticationProvider $twoFactor,
    ) {}

    /**
     * @throws ValidationException wrong code, or link no longer valid
     */
    public function handle(string $token, string $password, string $code): User
    {
        return DB::transaction(function () use ($token, $password, $code): User {
            $user = $this->links->userFor($token);

            /** @var User|null $locked */
            $locked = $user !== null ? User::query()->lockForUpdate()->find($user->id) : null;

            if ($locked === null || $locked->two_factor_confirmed_at === null || $locked->two_factor_secret === null) {
                throw ValidationException::withMessages(['code' => [Text::get('users.access.invalid')]]);
            }

            $secret = Fortify::currentEncrypter()->decrypt($locked->two_factor_secret);

            if (! is_string($secret) || ! $this->twoFactor->verify($secret, preg_replace('/\s+/', '', $code) ?? '')) {
                throw ValidationException::withMessages(['code' => [Text::get('auth.two_factor.invalid_code')]]);
            }

            $locked->forceFill(['password' => $password])->save();

            $this->links->delete($locked);

            return $locked;
        });
    }
}
