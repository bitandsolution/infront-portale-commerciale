<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Enums\FilePurpose;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Document;
use App\Models\ProposalGroupFile;
use App\Models\Slide;
use App\Models\StoredFile;
use App\Models\StoredFileDerivative;
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
 * - slide image: who manages the slide library, admin and manager (DM-02, D-406); the agent the
 *   images of the active slides, which the step "Documento" shows for his groups (DM-05; the slides
 *   are material of Infront, not data of a client);
 * - thumbnail: who may see its original (stored_file_derivatives, D-420);
 * - custom slide image: who may see the group it was uploaded for (proposal_group_files, DM-07):
 *   admin and manager every group, the agent his own;
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
            FilePurpose::SlideImage => Ability::ContentManage->allows($user->role)
                || (Ability::ProposalsOwn->allows($user->role) && Slide::query()->where('image_file_id', $file->id)->where('is_active', true)->exists()),
            FilePurpose::GeneratedPdf => $user->role === Role::Admin || $this->downloadsDocument($user, $file),
            FilePurpose::Thumbnail => $this->viewsOriginal($user, $file),
            // DM-06: photos of the fields of the LED simulation, Infront material; the agent sees
            // their thumbnail in the step "Documento" (D-426).
            FilePurpose::FieldPhoto => Ability::ContentManage->allows($user->role) || Ability::ProposalsOwn->allows($user->role),
            FilePurpose::CustomSlideImage => $this->viewsGroup($user, $file),
        };
    }

    private function viewsOriginal(User $user, StoredFile $file): bool
    {
        $original = StoredFileDerivative::query()->with('original')->where('derivative_file_id', $file->id)->first()?->original;

        return $original !== null && $this->view($user, $original);
    }

    private function viewsGroup(User $user, StoredFile $file): bool
    {
        $group = ProposalGroupFile::query()->with('group')->where('stored_file_id', $file->id)->first()?->group;

        return $group !== null && $user->can('view', $group);
    }

    private function downloadsDocument(User $user, StoredFile $file): bool
    {
        $document = Document::query()->with('group')->where('file_id', $file->id)->first();

        return $document !== null && $user->can('download', $document);
    }
}
