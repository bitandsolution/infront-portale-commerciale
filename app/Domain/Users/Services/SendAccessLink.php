<?php

namespace App\Domain\Users\Services;

use App\Domain\Notifications\Services\Notifications;
use App\Enums\AccessLinkType;
use App\Mail\AccessLinkMail;
use App\Models\User;

/**
 * Creates the token of an access link and sends it to the user by e-mail (M01 §5, §7; D-277).
 *
 * The link is never shown to the admin: only the user can set his password and second factor.
 * Since PR-08 the e-mail goes through the "mail" queue with the Infront template and is registered
 * in notification_logs (D-209, D-220). Called inside the transaction of the Action: the e-mail is
 * queued only after the commit, encrypted, so the token never travels in clear.
 */
final class SendAccessLink
{
    public function __construct(
        private readonly AccessLinks $links,
        private readonly Notifications $notifications,
    ) {}

    public function __invoke(User $user, AccessLinkType $type): void
    {
        $token = $this->links->create($user);

        $this->notifications->send($user, new AccessLinkMail(
            firstName: $user->first_name,
            type: $type,
            url: route($type->routeName(), ['token' => $token]),
        ));
    }
}
