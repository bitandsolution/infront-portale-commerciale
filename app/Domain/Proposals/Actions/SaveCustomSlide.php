<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Content\Services\CustomSlideContent;
use App\Domain\Proposals\Data\CustomSlideAuditValues;
use App\Domain\Proposals\Data\CustomSlideInput;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\GroupDocumentLock;
use App\Enums\AuditAction;
use App\Enums\CustomSlideLayout;
use App\Models\ProposalCustomSlide;
use App\Models\ProposalGroupFile;
use App\Models\SlideSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves the content of a slide composed with blocks (D-399, D-429, D-430; DM-07), sent by the editor
 * shortly after the last change: title, layout, section, image of the layout "image_text" and blocks.
 *
 * - Same permissions as the choice of the slides (GroupDocumentLock, D-397).
 * - The images can only be those uploaded for the group (proposal_group_files): any other id is left
 *   out; at most 2 images per slide (D-430). The request already says it under the field.
 * - The image of the slide is kept only by the layout "image_text"; changing layout keeps the blocks.
 * - Nothing changes: nothing is written, no event. Otherwise one audit event on the first variant with
 *   the fields changed.
 */
final class SaveCustomSlide
{
    public function __construct(
        private readonly GroupDocumentLock $lock,
        private readonly AuditLog $audit,
    ) {}

    public function handle(ProposalCustomSlide $slide, CustomSlideInput $input, User $actor): ProposalCustomSlide
    {
        return DB::transaction(function () use ($slide, $input, $actor): ProposalCustomSlide {
            [$locked, $first] = $this->lock->lock($slide->group, $actor);
            $slide = ProposalCustomSlide::query()->where('proposal_group_id', $locked->id)->whereKey($slide->id)->lockForUpdate()->firstOrFail();
            $before = CustomSlideAuditValues::of($slide);

            /** @var list<int> $groupFiles */
            $groupFiles = ProposalGroupFile::query()->where('proposal_group_id', $locked->id)->pluck('stored_file_id')->all();
            $blocks = array_map(function (array $block) use ($groupFiles): array {
                if (array_key_exists('file_id', $block) && ! in_array($block['file_id'], $groupFiles, true)) {
                    $block['file_id'] = null;
                }

                return $block;
            }, CustomSlideContent::normalize($input->blocks));

            if (CustomSlideContent::imageCount($input->layout, $blocks) > CustomSlideContent::MAX_IMAGES) {
                throw ProposalsActionNotAllowed::tooManyImages(CustomSlideContent::MAX_IMAGES);
            }

            $imageFileId = $input->layout === CustomSlideLayout::ImageText && in_array($input->imageFileId, $groupFiles, true)
                ? $input->imageFileId
                : null;
            $sectionCode = in_array($input->sectionCode, SlideSection::CUSTOM_SLIDE_SECTIONS, true) ? $input->sectionCode : SlideSection::BRAND_EXPOSURE;

            $slide->fill([
                'title' => mb_substr(trim($input->title), 0, CustomSlideContent::MAX_SLIDE_TITLE_LENGTH),
                'layout' => $input->layout,
                'slide_section_id' => SlideSection::query()->where('code', $sectionCode)->value('id'),
                'image_file_id' => $imageFileId,
                'blocks' => $blocks,
            ]);

            if (! $slide->isDirty()) {
                return $slide;
            }

            $slide->save();
            [$old, $new] = AuditLog::changes($before, CustomSlideAuditValues::of($slide));

            if ($new !== []) {
                $this->audit->record(
                    AuditAction::ProposalCustomSlideUpdated,
                    $actor,
                    AuditSubject::proposal($first),
                    $old,
                    ['proposal_group_id' => $locked->id, 'custom_slide_id' => $slide->id, ...$new],
                );
            }

            return $slide;
        });
    }
}
