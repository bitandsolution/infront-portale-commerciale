<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Proposals\Data\CustomSlideAuditValues;
use App\Domain\Proposals\Services\GroupDocumentLock;
use App\Enums\AuditAction;
use App\Models\ProposalCustomSlide;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a slide composed with blocks of a group (D-399; DM-07), after the confirmation in the
 * editor. Same permissions as the choice of the slides (GroupDocumentLock). The uploaded images stay
 * (files are never deleted, C10); the versions already frozen keep their copy. Audit
 * proposal.custom_slide_deleted with the content deleted.
 */
final class DeleteCustomSlide
{
    public function __construct(
        private readonly GroupDocumentLock $lock,
        private readonly AuditLog $audit,
    ) {}

    public function handle(ProposalCustomSlide $slide, User $actor): void
    {
        DB::transaction(function () use ($slide, $actor): void {
            [$locked, $first] = $this->lock->lock($slide->group, $actor);
            $slide = ProposalCustomSlide::query()->where('proposal_group_id', $locked->id)->whereKey($slide->id)->lockForUpdate()->firstOrFail();
            $before = CustomSlideAuditValues::of($slide);
            $slide->delete();

            $this->audit->record(
                AuditAction::ProposalCustomSlideDeleted,
                $actor,
                AuditSubject::proposal($first),
                $before,
                ['proposal_group_id' => $locked->id],
            );
        });
    }
}
