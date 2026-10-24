<?php

namespace App\Domain\Audit\Data;

use App\Models\User;

/**
 * Object an audit event talks about (M10 §4.2: type and identifier, e.g. "proposal n. 192").
 *
 * The type is a short stable name, not the class name, so a renamed class never breaks the trail.
 * The label is a readable description that stays when the object changes or disappears (M10 §8).
 */
final readonly class AuditSubject
{
    public function __construct(
        public string $type,
        public ?int $id,
        public ?string $label,
    ) {}

    public static function user(User $user): self
    {
        return new self('user', $user->id, $user->fullName().' ('.$user->email.')');
    }

    /**
     * Login attempted with an e-mail that matches no user: only what was typed (M10-CA2).
     */
    public static function attemptedEmail(string $email): self
    {
        return new self('user', null, mb_substr($email, 0, 255));
    }
}
