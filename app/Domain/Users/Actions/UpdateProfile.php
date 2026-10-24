<?php

namespace App\Domain\Users\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Users\Data\UserAuditValues;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Personal profile (M01 §5, D-267): every user changes his own name, surname and phone.
 * E-mail and role are changed only by the admin (D-139).
 *
 * Audit (D-127): user.profile_updated with the changed fields, the user as author, in the same
 * transaction; no event when nothing changes.
 */
final class UpdateProfile
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(User $user, string $firstName, string $lastName, ?string $phone): User
    {
        return DB::transaction(function () use ($user, $firstName, $lastName, $phone): User {
            $before = UserAuditValues::of($user);

            $user->fill([
                'first_name' => trim($firstName),
                'last_name' => trim($lastName),
                'phone' => CreateUser::phone($phone),
            ])->save();

            [$old, $new] = AuditLog::changes($before, UserAuditValues::of($user));

            if ($new !== []) {
                $this->audit->record(AuditAction::UserProfileUpdated, $user, AuditSubject::user($user), $old, $new);
            }

            return $user;
        });
    }
}
