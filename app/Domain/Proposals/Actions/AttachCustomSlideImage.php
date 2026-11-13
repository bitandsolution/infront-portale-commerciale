<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Content\Services\CustomSlideContent;
use App\Domain\Files\Services\FileStorage;
use App\Domain\Proposals\Data\CustomSlideAuditValues;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\GroupDocumentLock;
use App\Enums\AuditAction;
use App\Enums\CustomBlockType;
use App\Enums\CustomSlideLayout;
use App\Enums\FilePurpose;
use App\Models\ProposalCustomSlide;
use App\Models\ProposalGroupFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Uploads an image for a slide composed with blocks (D-399, D-430; DM-07): JPEG or PNG up to 5 MB,
 * checked on the content and re-encoded (FileStorage, D-218), recorded among the files of the group
 * (who sees the group sees it) and put in its place: the block with the given key (image or image
 * with text) or, without a key, the image of the layout "image_text". A new image is a new file (C10).
 * Same permissions as the choice of the slides; audit proposal.custom_slide_updated with the change.
 */
final class AttachCustomSlideImage
{
    public function __construct(
        private readonly GroupDocumentLock $lock,
        private readonly FileStorage $files,
        private readonly AuditLog $audit,
    ) {}

    public function handle(ProposalCustomSlide $slide, UploadedFile $image, ?string $blockKey, User $actor): ProposalCustomSlide
    {
        return DB::transaction(function () use ($slide, $image, $blockKey, $actor): ProposalCustomSlide {
            [$locked, $first] = $this->lock->lock($slide->group, $actor);
            $slide = ProposalCustomSlide::query()->where('proposal_group_id', $locked->id)->whereKey($slide->id)->lockForUpdate()->firstOrFail();
            $before = CustomSlideAuditValues::of($slide);
            $blocks = $slide->blocks;
            $target = null;

            if ($blockKey === null) {
                if ($slide->layout !== CustomSlideLayout::ImageText) {
                    throw ProposalsActionNotAllowed::customImageTarget();
                }
            } else {
                foreach ($blocks as $index => $block) {
                    if (($block['key'] ?? null) === $blockKey && CustomBlockType::tryFrom((string) ($block['type'] ?? ''))?->hasImage() === true) {
                        $target = $index;
                    }
                }

                if ($target === null) {
                    throw ProposalsActionNotAllowed::customImageTarget();
                }
            }

            $file = $this->files->storeUpload($image, FilePurpose::CustomSlideImage, $actor);
            ProposalGroupFile::query()->create(['proposal_group_id' => $locked->id, 'stored_file_id' => $file->id]);

            if ($target === null) {
                $slide->image_file_id = $file->id;
            } else {
                $blocks[$target]['file_id'] = $file->id;
                $slide->blocks = CustomSlideContent::normalize($blocks);
            }

            $slide->save();
            [$old, $new] = AuditLog::changes($before, CustomSlideAuditValues::of($slide));

            $this->audit->record(
                AuditAction::ProposalCustomSlideUpdated,
                $actor,
                AuditSubject::proposal($first),
                $old,
                ['proposal_group_id' => $locked->id, 'custom_slide_id' => $slide->id, ...$new],
            );

            return $slide;
        });
    }
}
