<?php

namespace App\Domain\Users\Auth;

use Illuminate\Http\Request;

/**
 * The fifth failed attempt has just locked an e-mail and IP pair for 15 minutes (M01-CA2, D-274).
 *
 * Dispatched once, when the lock starts. Laravel's Lockout event is not enough: Fortify dispatches
 * it also at every attempt made during the lock (EnsureLoginIsNotThrottled), which the audit records
 * as failed logins instead (D-283). Listened to by the audit (PR-05) and by the e-mails to the user
 * and the admin (PR-08, M01 §7).
 */
final readonly class LoginLockedOut
{
    public function __construct(
        public Request $request,
        public string $email,
    ) {}
}
