<?php

namespace App\Domain\Users\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checks e-mail and password for Fortify's login steps (Fortify::authenticateUsing).
 *
 * - Wrong e-mail or wrong password: null, so Fortify answers with the generic
 *   "invalid credentials" message and counts the attempt (D-214: the message never says
 *   whether the e-mail exists). A password hash is checked in both cases, so the answer
 *   takes the same time.
 * - Right password of a deactivated user: the dedicated message of PR-02 (M01 §5, RB-05),
 *   counted as a failed attempt as well.
 * - User not activated yet (PR-04): null as for a wrong password. His password is random anyway;
 *   he enters only through the activation link (D-278).
 */
class AuthenticateUser
{
    public function __construct(private readonly LoginRateLimiter $limiter) {}

    public function __invoke(Request $request): ?User
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (! is_string($email) || ! is_string($password)) {
            return null;
        }

        $user = User::query()->where('email', Str::lower(trim($email)))->first();

        // Same work when the user does not exist (timing, see above).
        $hash = $user !== null ? $user->password : Hash::make(Str::random(40));

        if (! Hash::check($password, $hash) || $user === null || $user->activated_at === null) {
            return null;
        }

        if (! $user->is_active) {
            $this->limiter->increment($request);

            throw ValidationException::withMessages([
                'email' => [__('ui.auth.deactivated')],
            ]);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        return $user;
    }
}
