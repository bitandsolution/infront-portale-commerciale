<?php

namespace App\Http\Responses\Auth;

use App\Domain\Users\Auth\LoginRateLimiter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LockoutResponse as LockoutResponseContract;

/**
 * Answer while an e-mail + IP pair is locked (M01-CA2, D-214): back to the login with
 * "Troppi tentativi. Riprova tra N minuti." The page keeps the password field disabled
 * until the time is up (props.lockedSeconds, from the flashed end of the lock).
 */
class LockoutResponse implements LockoutResponseContract
{
    public const SESSION_KEY = 'login.locked_until';

    public function __construct(private readonly LoginRateLimiter $limiter) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): never
    {
        throw self::exception($request, $this->limiter->availableIn($request));
    }

    public static function exception(Request $request, int $seconds): ValidationException
    {
        $seconds = max(1, $seconds);

        if ($request->hasSession()) {
            $request->session()->flash(self::SESSION_KEY, now()->addSeconds($seconds)->getTimestamp());
        }

        return ValidationException::withMessages([
            'email' => [__('auth.throttle', ['minutes' => (string) (int) ceil($seconds / 60)])],
        ])->redirectTo(route('login'));
    }
}
