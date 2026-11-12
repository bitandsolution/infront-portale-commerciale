<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Documents\Exceptions\DocumentsActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ProposalStatus;
use App\Jobs\GenerateDocument;
use App\Models\Document;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\ProposalVersionClub;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Asks for the PDF document of an approved group (M09 FL-01, F-01; D-090, D-120, D-125, D-205,
 * D-413, D-414; DM-04): called by the approval, in its transaction, and by the regeneration of the
 * admin. The current document, if any, becomes "replaced" (it stays, downloadable by the admin only),
 * a new row starts "queued" and the job of the queue "documents" composes and renders it after the
 * commit. A job of a replaced document finds it replaced and stores nothing.
 */
final class RequestDocument
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(ProposalGroup $group, User $actor, bool $regeneration = false): Document
    {
        return DB::transaction(function () use ($group, $actor, $regeneration): Document {
            ProposalGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();

            $variants = Proposal::query()
                ->where('proposal_group_id', $group->id)
                ->where('status', ProposalStatus::Approved->value)
                ->whereNotNull('current_version_id')
                ->orderBy('variant_letter')
                ->get();

            /** @var Proposal|null $first */
            $first = $variants->first();
            $versionIds = $variants->pluck('current_version_id')->filter()->all();
            $clubs = ProposalVersionClub::query()->whereIn('proposal_version_id', $versionIds)->distinct()->count('club_id');
            $type = DocumentType::forClubs($clubs);

            if ($first === null || $first->current_version_id === null || $type === null) {
                throw DocumentsActionNotAllowed::groupNotApproved();
            }

            $current = Document::query()
                ->where('proposal_group_id', $group->id)
                ->where('format', Document::FORMAT_PDF)
                ->where('language', Document::LANGUAGE)
                ->whereNull('superseded_at')
                ->lockForUpdate()
                ->first();

            $current?->forceFill(['superseded_at' => now()])->save();

            $document = Document::query()->create([
                'proposal_group_id' => $group->id,
                'proposal_id' => $first->id,
                'proposal_version_id' => $first->current_version_id,
                'document_type' => $type,
                'format' => Document::FORMAT_PDF,
                'language' => Document::LANGUAGE,
                'status' => DocumentStatus::Queued,
                'attempts' => 0,
                // progettazione-tecnica §5.3.5: null = automatic (after the approval).
                'generated_by' => $regeneration ? $actor->id : null,
            ]);

            $current?->forceFill(['superseded_by_id' => $document->id])->save();

            $this->audit->record(
                $regeneration ? AuditAction::DocumentRegenerated : AuditAction::DocumentRequested,
                $actor,
                AuditSubject::document($document, $first),
                $current !== null ? ['document_id' => $current->id, 'status' => $current->status->value] : [],
                [
                    'document_id' => $document->id,
                    'proposal_group_id' => $group->id,
                    'document_type' => $type->value,
                    'variants' => $variants->pluck('variant_letter')->implode(', '),
                ],
            );

            GenerateDocument::dispatch($document->id)->afterCommit();

            return $document;
        });
    }
}
