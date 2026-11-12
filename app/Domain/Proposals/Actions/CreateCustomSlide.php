<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Content\Services\CustomSlideContent;
use App\Domain\Proposals\Data\CustomSlideAuditValues;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\GroupDocumentLock;
use App\Enums\AuditAction;
use App\Enums\CustomBlockType;
use App\Enums\CustomSlideLayout;
use App\Models\ProposalCustomSlide;
use App\Models\ProposalGroup;
use App\Models\SlideSection;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Aggiungi slide personalizzata" in the step "Documento" or in the detail of the group (D-399;
 * DM-07): a new slide composed with blocks, at most three per group, ready to fill: "Slide
 * personalizzata N", one column, at the end of the section Brand exposure, right before the recap
 * (D-429), with an empty title and an empty text. Same permissions as the choice of the slides
 * (GroupDocumentLock); audit proposal.custom_slide_created on the first variant.
 */
final class CreateCustomSlide
{
    public function __construct(
        private readonly GroupDocumentLock $lock,
        private readonly AuditLog $audit,
    ) {}

    public function handle(ProposalGroup $group, User $actor): ProposalCustomSlide
    {
        return DB::transaction(function () use ($group, $actor): ProposalCustomSlide {
            [$locked, $first] = $this->lock->lock($group, $actor);

            if ($locked->customSlides()->count() >= ProposalCustomSlide::MAX_PER_GROUP) {
                throw ProposalsActionNotAllowed::tooManyCustomSlides(ProposalCustomSlide::MAX_PER_GROUP);
            }

            $order = ((int) $locked->customSlides()->max('sort_order')) + 1;
            $slide = ProposalCustomSlide::query()->create([
                'proposal_group_id' => $locked->id,
                'sort_order' => $order,
                'title' => Text::get('proposals.custom_slides.default_title', ['number' => (string) $order]),
                'layout' => CustomSlideLayout::OneColumn,
                'slide_section_id' => SlideSection::query()->where('code', SlideSection::BRAND_EXPOSURE)->value('id'),
                'blocks' => [
                    CustomSlideContent::emptyBlock(CustomBlockType::Title, Str::lower(Str::random(8))),
                    CustomSlideContent::emptyBlock(CustomBlockType::Text, Str::lower(Str::random(8))),
                ],
            ]);

            $this->audit->record(
                AuditAction::ProposalCustomSlideCreated,
                $actor,
                AuditSubject::proposal($first),
                [],
                ['proposal_group_id' => $locked->id, ...CustomSlideAuditValues::of($slide)],
            );

            return $slide;
        });
    }
}
