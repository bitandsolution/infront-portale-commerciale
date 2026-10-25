<?php

namespace App\Policies;

use App\Enums\FilePurpose;
use App\Enums\Role;
use App\Models\StoredFile;
use App\Models\User;

/**
 * Download of the stored files (D-218, D-215): the only way to read a file of the private disk.
 *
 * Who may see a file depends on the entity that uses it, so each slice replaces the case of its
 * purpose with a check on that entity, with its own tests:
 * - client logo: who can see the client (F3);
 * - club logo: every active user (F1-02, D-321): logos are not reserved and the agent sees them in
 *   the configurator (F4); calendar import: back office (F1-04);
 * - slide image: back office (F6);
 * - generated PDF: who can download the document, the replaced ones only the admin (F7, D-205).
 *
 * The purposes not handled yet stay admin only (PR-06), e.g. for the checks on the development VM.
 * Deactivated users are already refused by Gate::before (AppServiceProvider).
 */
class StoredFilePolicy
{
    public function view(User $user, StoredFile $file): bool
    {
        return match ($file->purpose) {
            FilePurpose::ClubLogo => true,
            FilePurpose::ClientLogo,
            FilePurpose::SlideImage,
            FilePurpose::CalendarImport,
            FilePurpose::GeneratedPdf => $user->role === Role::Admin,
        };
    }
}
