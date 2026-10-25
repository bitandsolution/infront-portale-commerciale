<?php

namespace App\Listeners;

use App\Domain\Notifications\Services\Notifications;
use App\Domain\Users\Auth\LoginLockedOut;
use App\Domain\Users\Auth\LoginRateLimiter;
use App\Enums\Role;
use App\Mail\LoginLockedAdminMail;
use App\Mail\LoginLockedMail;
use App\Models\User;
use App\Support\Text;
use App\Support\Users\UserPresenter;
use Throwable;

/**
 * E-mails of the lock after 5 failed attempts (M01 §7; D-274, D-285; PR-08), once per lock: the
 * event LoginLockedOut is dispatched only when the lock starts.
 *
 * - Only for an existing, active and activated user: an unknown address sends nothing (whoever
 *   tries random addresses would flood the admins); deactivated or not yet activated users cannot
 *   sign in anyway.
 * - To the user: when and how long, no IP address.
 * - To every active admin but the locked user himself: who, when, from which IP address. An admin
 *   who locks himself receives only the user's e-mail.
 *
 * A failure here never changes the answer of the login page: it is reported and the lock stays.
 */
class SendLockoutNotifications
{
    public function __construct(private readonly Notifications $notifications) {}

    public function handle(LoginLockedOut $event): void
    {
        try {
            $this->send($event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function send(LoginLockedOut $event): void
    {
        $email = mb_strtolower(trim($event->email));
        $user = $email === '' ? null : User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->whereNotNull('activated_at')
            ->first();

        if ($user === null) {
            return;
        }

        $minutes = intdiv(LoginRateLimiter::LOCKOUT_SECONDS, 60);
        $now = now()->setTimezone(UserPresenter::TIMEZONE);
        $lockedAt = Text::get('mail.datetime', ['date' => $now->format('d/m/Y'), 'time' => $now->format('H:i')]);

        $this->notifications->send($user, new LoginLockedMail(
            firstName: $user->first_name,
            lockedAt: $lockedAt,
            minutes: $minutes,
            loginUrl: route('login'),
        ));

        $admins = User::query()
            ->where('role', Role::Admin->value)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->orderBy('id')
            ->get();

        foreach ($admins as $admin) {
            $this->notifications->send($admin, new LoginLockedAdminMail(
                adminFirstName: $admin->first_name,
                userName: $user->fullName(),
                userEmail: $user->email,
                userRole: $user->role->label(),
                lockedAt: $lockedAt,
                ipAddress: (string) $event->request->ip(),
                minutes: $minutes,
                userUrl: route('users.show', ['user' => $user->id]),
            ));
        }
    }
}
