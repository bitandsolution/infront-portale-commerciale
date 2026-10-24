<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Users\Auth\LoginRateLimiter;
use App\Http\Responses\Auth\LockoutResponse;
use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

/**
 * Second step of every login (D-136, D-214): code from the authenticator app or one of the
 * single-use recovery codes. Fortify's controller, plus the lockout shared with the password:
 * while the e-mail + IP pair is locked no code is accepted, and a successful login clears it.
 */
class TwoFactorChallengeController extends TwoFactorAuthenticatedSessionController
{
    public function __construct(StatefulGuard $guard, private readonly LoginRateLimiter $limiter)
    {
        parent::__construct($guard);
    }

    public function store(TwoFactorLoginRequest $request): mixed
    {
        /** @var User $user */
        $user = $request->challengedUser();

        if ($this->limiter->isLocked($user->email, $request)) {
            $request->session()->forget(['login.id', 'login.remember']);

            throw LockoutResponse::exception($request, $this->limiter->availableInFor($user->email, $request));
        }

        $response = parent::store($request);

        // Signed in only when the code was right: the wrong-code answer leaves the user a guest.
        if ($this->guard->check()) {
            $this->limiter->clearFor($user->email, $request);
        }

        return $response;
    }
}
