<?php

namespace App\Domain\Users\Auth;

use App\Http\Responses\Auth\LockoutResponse;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\LoginRateLimiter as FortifyLoginRateLimiter;

/**
 * Login lockout (M01 §6, D-214; progettazione-tecnica §5.4.2): 5 failed attempts for the same
 * e-mail and IP address within 15 minutes lock that pair for 15 minutes, counted from the
 * attempt that triggers the lock. Counters live in the cache store (Redis, D-209).
 *
 * Wrong passwords and wrong two-factor codes count alike, so the second factor cannot be
 * guessed by signing in again with the password. The e-mail does not need to exist: the
 * answer is the same for every address (the message never reveals whether an account exists).
 *
 * Replaces Fortify's limiter (fixed 60-second window) in the container, so Fortify's own
 * login steps use these rules.
 */
class LoginRateLimiter extends FortifyLoginRateLimiter
{
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 900;

    /**
     * @return int
     */
    public function attempts(Request $request)
    {
        return $this->attemptsFor($this->email($request), $request);
    }

    /**
     * @return bool
     */
    public function tooManyAttempts(Request $request)
    {
        return $this->isLocked($this->email($request), $request);
    }

    /**
     * Counts a failed attempt; the fifth one starts the lock and answers with the lockout message
     * right away, instead of the generic "invalid credentials".
     *
     * @return void
     */
    public function increment(Request $request)
    {
        $this->incrementFor($this->email($request), $request);
    }

    /**
     * @return int
     */
    public function availableIn(Request $request)
    {
        return $this->availableInFor($this->email($request), $request);
    }

    /**
     * @return void
     */
    public function clear(Request $request)
    {
        $this->clearFor($this->email($request), $request);
    }

    public function attemptsFor(string $email, Request $request): int
    {
        return (int) $this->limiter->attempts($this->key($email, $request));
    }

    public function isLocked(string $email, Request $request): bool
    {
        return $this->limiter->tooManyAttempts($this->lockKey($email, $request), 1);
    }

    public function incrementFor(string $email, Request $request): void
    {
        $key = $this->key($email, $request);

        if ($this->limiter->hit($key, self::LOCKOUT_SECONDS) < self::MAX_ATTEMPTS) {
            return;
        }

        $this->limiter->clear($key);
        $this->limiter->hit($this->lockKey($email, $request), self::LOCKOUT_SECONDS);

        // Listened to by the audit from PR-05 and by the e-mails from PR-08 (M01 §7).
        event(new Lockout($request));

        throw LockoutResponse::exception($request, self::LOCKOUT_SECONDS);
    }

    public function availableInFor(string $email, Request $request): int
    {
        return max(0, $this->limiter->availableIn($this->lockKey($email, $request)));
    }

    public function clearFor(string $email, Request $request): void
    {
        $this->limiter->clear($this->key($email, $request));
        $this->limiter->clear($this->lockKey($email, $request));
    }

    /**
     * @return string
     */
    protected function throttleKey(Request $request)
    {
        return $this->key($this->email($request), $request);
    }

    private function key(string $email, Request $request): string
    {
        return 'login:'.Str::transliterate(Str::lower(trim($email))).'|'.$request->ip();
    }

    private function lockKey(string $email, Request $request): string
    {
        return 'login-lock:'.Str::transliterate(Str::lower(trim($email))).'|'.$request->ip();
    }

    private function email(Request $request): string
    {
        $email = $request->input('email');

        return is_string($email) ? $email : '';
    }
}
