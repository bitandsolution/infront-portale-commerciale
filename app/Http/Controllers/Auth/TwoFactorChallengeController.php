<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Services\AuditLog;
use App\Domain\Users\Auth\LoginRateLimiter;
use App\Enums\AccessReason;
use App\Enums\AuditAction;
use App\Http\Responses\Auth\LockoutResponse;
use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

/**
 * Second step of every login (D-136, D-214): code from the authenticator app or one of the
 * single-use recovery codes. Fortify's controller, plus the lockout shared with the password:
 * while the e-mail + IP pair is locked no code is accepted, and a successful login clears it.
 *
 * Audit (D-128, D-283): a code sent during the lock is a failed login with reason "locked"; a login
 * completed with a recovery code records also access.recovery_code_used (never the code itself).
 * Wrong codes are recorded from Fortify's TwoFactorAuthenticationFailed event (RecordAccessAudit).
 */
class TwoFactorChallengeController extends TwoFactorAuthenticatedSessionController
{
    public function __construct(
        StatefulGuard $guard,
        private readonly LoginRateLimiter $limiter,
        private readonly AuditLog $audit,
    ) {
        parent::__construct($guard);
    }

    public function store(TwoFactorLoginRequest $request): mixed
    {
        /** @var User $user */
        $user = $request->challengedUser();

        if ($this->limiter->isLocked($user->email, $request)) {
            $request->session()->forget(['login.id', 'login.remember']);
            $this->audit->access(AuditAction::LoginFailed, $user, reason: AccessReason::Locked->value);

            throw LockoutResponse::exception($request, $this->limiter->availableInFor($user->email, $request));
        }

        $response = parent::store($request);

        // Signed in only when the code was right: the wrong-code answer leaves the user a guest.
        if ($this->guard->check()) {
            $this->limiter->clearFor($user->email, $request);

            if ($request->filled('recovery_code')) {
                $this->audit->access(AuditAction::RecoveryCodeUsed, $user);
            }
        }

        return $response;
    }
}
