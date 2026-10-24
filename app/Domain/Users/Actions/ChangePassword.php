<?php

namespace App\Domain\Users\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Change of the password from the profile (M01 §5; D-214, D-273). The current password is checked
 * by the request (current_password rule), the new one follows Password::defaults().
 *
 * The new hash closes every other session of the user (auth.session). The session that made the
 * change stays open: it is the same User instance of the request, whose new hash auth.session
 * stores at the end of the request.
 *
 * Audit (D-127): user.password_changed, the user as author, without values.
 */
final class ChangePassword
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(User $user, string $password): User
    {
        return DB::transaction(function () use ($user, $password): User {
            $user->forceFill(['password' => $password])->save();

            $this->audit->record(AuditAction::UserPasswordChanged, $user, AuditSubject::user($user));

            return $user;
        });
    }
}
