<?php

namespace App\Domain\Users\Services;

use App\Enums\AccessLinkType;
use App\Mail\AccessLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Creates the token of an access link and sends it to the user by e-mail (M01 §5, §7; D-277).
 *
 * The link is never shown to the admin: only the user can set his password and second factor.
 * Single entry point for the e-mail: PR-08 changes this class (queue, Infront template,
 * notification_logs) and the Actions stay as they are.
 */
final class SendAccessLink
{
    public function __construct(private readonly AccessLinks $links) {}

    public function __invoke(User $user, AccessLinkType $type): void
    {
        $token = $this->links->create($user);

        Mail::to($user->email)->send(new AccessLinkMail(
            firstName: $user->first_name,
            type: $type,
            url: route($type->routeName(), ['token' => $token]),
        ));
    }
}
