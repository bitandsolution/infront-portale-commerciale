<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Enums\Role;
use App\Models\Document;
use App\Models\User;

/**
 * Documents of the groups (M09 §3; D-090, D-205, D-417; DM-04):
 * - download: the current document by admin and manager (DocumentsDownloadAll) and by the agent of
 *   the group (his own approved proposals, D-090); a replaced document only by the admin (D-205);
 * - regenerate: the admin (DocumentsRegenerate, D-125).
 * Deactivated users are already refused by Gate::before.
 */
class DocumentPolicy
{
    public function download(User $user, Document $document): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (! $document->isCurrent()) {
            return false;
        }

        return Ability::DocumentsDownloadAll->allows($user->role)
            || ($user->role === Role::Agent && $document->group->isOwnedBy($user));
    }

    public function regenerate(User $user): bool
    {
        return Ability::DocumentsRegenerate->allows($user->role);
    }
}
