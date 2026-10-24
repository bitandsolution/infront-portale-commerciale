<?php

namespace App\Domain\Users\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The admin reactivates a deactivated user (M01 §5). Password and second factor are kept
 * (D-281): the user signs in as before.
 *
 * Audit (D-127): user.reactivated, author $actor, in the same transaction.
 */
final class ReactivateUser
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor): User {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->is_active) {
                return $locked;
            }

            $locked->forceFill([
                'is_active' => true,
                'deactivated_at' => null,
                'deactivated_by' => null,
            ])->save();

            $this->audit->record(AuditAction::UserReactivated, $actor, AuditSubject::user($locked), ['is_active' => false], ['is_active' => true]);

            return $locked;
        });
    }
}
