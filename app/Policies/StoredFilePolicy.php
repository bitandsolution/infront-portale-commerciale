<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Enums\FilePurpose;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Document;
use App\Models\StoredFile;
use App\Models\User;

/**
 * Download of the stored files (D-218, D-215): the only way to read a file of the private disk.
 *
 * Who may see a file depends on the entity that uses it, so each slice replaces the case of its
 * purpose with a check on that entity, with its own tests:
 * - client logo: the admin every logo; the others the current logo of a client they can see (F3-02:
 *   the agent his own clients, the manager every client). A replaced logo stays admin only until the
 *   documents of F7 point to it (RB-07);
 * - club logo: every active user (F1-02, D-321): logos are not reserved and the agent sees them in
 *   the configurator (F4); calendar import: who manages the calendars, admin and manager (F1-04,
 *   D-321);
 * - slide image: who manages the slide library, admin and manager (DM-02, D-406); the agent sees the
 *   slides of his own groups from DM-05;
 * - generated PDF: who can download its document (DocumentPolicy::download, DM-04): admin and manager
 *   the current documents, the agent those of his own groups, the replaced ones only the admin
 *   (D-205); a PDF without a document (the proof of documents:try) only the admin.
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
            FilePurpose::CalendarImport => Ability::SportsCalendarsManage->allows($user->role),
            FilePurpose::ClientLogo => $user->role === Role::Admin
                || Client::query()->visibleTo($user)->where('logo_file_id', $file->id)->exists(),
            FilePurpose::SlideImage => Ability::ContentManage->allows($user->role),
            FilePurpose::GeneratedPdf => $user->role === Role::Admin || $this->downloadsDocument($user, $file),
        };
    }

    private function downloadsDocument(User $user, StoredFile $file): bool
    {
        $document = Document::query()->with('group')->where('file_id', $file->id)->first();

        return $document !== null && $user->can('download', $document);
    }
}
