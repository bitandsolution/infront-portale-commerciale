<?php

namespace App\Http\Responses\Auth;

use App\Domain\Users\Auth\LoginRateLimiter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse as FailedTwoFactorLoginResponseContract;

/**
 * Wrong code at the second step of the login (D-214). The attempt counts towards the same
 * lockout as the password (e-mail + IP, 5 attempts): at the fifth the pending login is dropped
 * and the user goes back to the login page with the lockout message.
 */
class FailedTwoFactorLoginResponse implements FailedTwoFactorLoginResponseContract
{
    public function __construct(private readonly LoginRateLimiter $limiter) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        $id = $request->session()->get('login.id');
        $user = $id !== null ? User::query()->find($id) : null;

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        try {
            $this->limiter->incrementFor($user->email, $request);
        } finally {
            if ($this->limiter->isLocked($user->email, $request)) {
                $request->session()->forget(['login.id', 'login.remember']);
            }
        }

        $field = $request->filled('recovery_code') ? 'recovery_code' : 'code';

        return redirect()->route('two-factor.login')->withErrors([
            $field => __($field === 'code' ? 'auth.two_factor.invalid_code' : 'auth.two_factor.invalid_recovery_code'),
        ]);
    }
}
