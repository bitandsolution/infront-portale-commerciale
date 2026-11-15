<?php

namespace App\Domain\Proposals\Services;

use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Lock of the work on a group waiting for approval (D-216, D-446; M07 F-09; F5-01): who holds it
 * and since when, written the same on every variant of the group (`review_locked_by`,
 * `review_locked_at` of the proposals, D-449). It lasts 30 minutes from the last action of the
 * holder (D-450): an expired lock counts as free. The Actions read it inside their transactions,
 * after locking the variants.
 */
final class ReviewLock
{
    public const MINUTES = 30;

    /**
     * The variant that carries the lock (every variant of the group has the same values).
     *
     * @param  iterable<Proposal>  $variants
     */
    public static function holderId(iterable $variants): ?int
    {
        foreach ($variants as $variant) {
            if ($variant->status === ProposalStatus::Submitted && $variant->review_locked_by !== null
                && $variant->review_locked_at !== null
                && $variant->review_locked_at->greaterThan(now()->subMinutes(self::MINUTES))) {
                return $variant->review_locked_by;
            }
        }

        return null;
    }

    /**
     * Moment of the last action of the holder of an active lock.
     *
     * @param  iterable<Proposal>  $variants
     */
    public static function since(iterable $variants): ?Carbon
    {
        foreach ($variants as $variant) {
            if (self::holderId([$variant]) !== null) {
                return $variant->review_locked_at;
            }
        }

        return null;
    }

    /**
     * @param  iterable<Proposal>  $variants
     */
    public static function heldByOther(iterable $variants, User $actor): bool
    {
        $holder = self::holderId($variants);

        return $holder !== null && $holder !== $actor->id;
    }

    /**
     * A change by admin or manager on a group waiting for approval: refused when another user holds
     * the lock; renews the lock of its holder (D-450). Nothing for a group not waiting.
     *
     * @param  iterable<Proposal>  $variants  locked by the caller
     */
    public static function guard(iterable $variants, User $actor): void
    {
        $holder = self::holderId($variants);

        if ($holder === null) {
            return;
        }

        if ($holder !== $actor->id) {
            throw ProposalsActionNotAllowed::reviewLocked(self::name($holder));
        }

        self::write($variants, $actor, now());
    }

    /**
     * Writes holder and moment on every variant (null = free), without touching `lock_version`:
     * the lock is not a change of the proposal.
     *
     * @param  iterable<Proposal>  $variants
     */
    public static function write(iterable $variants, ?User $holder, ?Carbon $at): void
    {
        foreach ($variants as $variant) {
            $variant->forceFill([
                'review_locked_by' => $holder?->id,
                'review_locked_at' => $holder !== null ? $at : null,
            ])->save();
        }
    }

    /** Name of a user for the messages ("Marta Gestori"). */
    public static function name(int $userId): string
    {
        $user = User::query()->find($userId);

        return $user instanceof User ? $user->first_name.' '.$user->last_name : '';
    }
}
