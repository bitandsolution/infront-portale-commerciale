<?php

namespace App\Domain\Users\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Users\Services\AccessLinks;
use App\Enums\AuditAction;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

/**
 * Activation from the link (M01 §5; D-209, D-278), also used after a reset of the second factor
 * (D-280): password and second factor are saved together, in one transaction, only once the
 * code of the authenticator app is right. Until then nothing changes in the database: a user
 * who leaves halfway reopens the same link.
 *
 * The token is checked again inside the transaction and deleted: the link works once.
 *
 * Audit (D-127): user.activated at the first activation, user.two_factor_configured after a reset
 * of the second factor; the user as author, in the same transaction.
 */
final class ActivateAccount
{
    public function __construct(
        private readonly AccessLinks $links,
        private readonly TwoFactorAuthenticationProvider $twoFactor,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @param  string  $secret  TOTP key shown on the page (kept in the session until now)
     * @param  string  $code  code of the authenticator app for that key
     *
     * @throws ValidationException wrong code, or link no longer valid
     */
    public function handle(string $token, string $password, string $secret, string $code): User
    {
        if (! $this->twoFactor->verify($secret, preg_replace('/\s+/', '', $code) ?? '')) {
            throw ValidationException::withMessages(['code' => [Text::get('auth.two_factor.invalid_code')]]);
        }

        return DB::transaction(function () use ($token, $password, $secret): User {
            $user = $this->links->userFor($token);

            /** @var User|null $locked */
            $locked = $user !== null ? User::query()->lockForUpdate()->find($user->id) : null;

            if ($locked === null || $locked->two_factor_confirmed_at !== null) {
                throw ValidationException::withMessages(['code' => [Text::get('users.access.invalid')]]);
            }

            $firstActivation = $locked->activated_at === null;

            $locked->forceFill([
                'password' => $password,
                'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
                'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt((string) json_encode(
                    array_map(fn (): string => RecoveryCode::generate(), range(1, 8)),
                )),
                'two_factor_confirmed_at' => now(),
                'activated_at' => $locked->activated_at ?? now(),
            ])->save();

            $this->links->delete($locked);

            $this->audit->record(
                $firstActivation ? AuditAction::UserActivated : AuditAction::UserTwoFactorConfigured,
                $locked,
                AuditSubject::user($locked),
            );

            return $locked;
        });
    }
}
