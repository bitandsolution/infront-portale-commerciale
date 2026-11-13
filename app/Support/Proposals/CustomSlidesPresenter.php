<?php

namespace App\Support\Proposals;

use App\Domain\Content\Data\Composition;
use App\Domain\Content\Data\CustomSlide;
use App\Domain\Content\Services\CustomSlideContent;
use App\Domain\Documents\Exceptions\DocumentToolUnavailable;
use App\Domain\Documents\Layouts\CustomBlocksLayout;
use App\Domain\Documents\Services\CustomSlideImages;
use App\Domain\Documents\Services\TextFitter;
use App\Enums\CustomBlockType;
use App\Enums\CustomSlideLayout;
use App\Models\ProposalCustomSlide;
use App\Models\ProposalGroup;
use App\Models\SlideSection;
use App\Support\Text;

/**
 * The editor of the slides composed with blocks of a group (D-399, D-428…D-432; DM-07), part of the
 * prop "documentSlides" of the step "Documento" and of the detail of the group: the slides with their
 * content, the options of the editor, the limits (D-430), the addresses of the Actions and, for every
 * slide, whether its content is reduced or cut in the PDF (D-431), measured with the same
 * CustomBlocksLayout of the PDF. Once approved the slides frozen with the versions, read only.
 */
final class CustomSlidesPresenter
{
    public function __construct(
        private readonly TextFitter $fitter,
        private readonly CustomSlideImages $images,
    ) {}

    /**
     * @param  list<CustomSlide>  $slides
     * @return array<string, mixed>
     */
    public function present(ProposalGroup $group, array $slides, Composition $composition, bool $editable): array
    {
        $hasBackground = false;

        foreach ($composition->slides as $composed) {
            $hasBackground = $hasBackground || $composed->custom !== null;
        }

        usort($slides, fn (CustomSlide $a, CustomSlide $b): int => [$a->sortOrder, $a->id] <=> [$b->sortOrder, $b->id]);

        return [
            'editable' => $editable,
            'max' => ProposalCustomSlide::MAX_PER_GROUP,
            'canAdd' => $editable && count($slides) < ProposalCustomSlide::MAX_PER_GROUP,
            'storeUrl' => route('proposals.groups.custom-slides.store', $group),
            'limits' => [
                'blocks' => CustomSlideContent::MAX_BLOCKS,
                'images' => CustomSlideContent::MAX_IMAGES,
                'rows' => CustomSlideContent::MAX_TABLE_ROWS,
                'columns' => CustomSlideContent::MAX_TABLE_COLUMNS,
                'title' => CustomSlideContent::MAX_TITLE_LENGTH,
                'text' => CustomSlideContent::MAX_TEXT_LENGTH,
                'cell' => CustomSlideContent::MAX_CELL_LENGTH,
                'slideTitle' => CustomSlideContent::MAX_SLIDE_TITLE_LENGTH,
            ],
            'layouts' => array_map(fn (CustomSlideLayout $layout): array => ['value' => $layout->value, 'label' => $layout->label()], CustomSlideLayout::cases()),
            'blockTypes' => array_map(fn (CustomBlockType $type): array => ['value' => $type->value, 'label' => $type->label()], CustomBlockType::cases()),
            'sections' => SlideSection::query()
                ->whereIn('code', SlideSection::CUSTOM_SLIDE_SECTIONS)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (SlideSection $section): array => ['value' => $section->code, 'label' => $section->name])
                ->values()
                ->all(),
            'noBackground' => $slides !== [] && ! $hasBackground && $composition->documentType !== null ? Text::get('proposals.custom_slides.no_background') : null,
            'slides' => array_map(fn (CustomSlide $slide): array => $this->slide($group, $slide, $editable), $slides),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function slide(ProposalGroup $group, CustomSlide $slide, bool $editable): array
    {
        $parameters = ['group' => $group, 'customSlide' => $slide->id];
        [$warning, $reduced] = $this->fit($slide);

        return [
            'id' => $slide->id,
            'title' => $slide->title,
            'layout' => $slide->layout->value,
            'section' => $slide->sectionCode,
            'included' => $slide->included,
            'imageFileId' => $slide->imageFileId,
            'imageUrl' => $slide->imageFileId !== null ? route('files.thumbnail', $slide->imageFileId) : null,
            'blocks' => array_map(fn (array $block): array => [
                ...$block,
                'imageUrl' => is_int($block['file_id'] ?? null) ? route('files.thumbnail', $block['file_id']) : null,
            ], $slide->blocks),
            'updateUrl' => $editable ? route('proposals.groups.custom-slides.update', $parameters) : null,
            'deleteUrl' => $editable ? route('proposals.groups.custom-slides.destroy', $parameters) : null,
            'uploadUrl' => $editable ? route('proposals.groups.custom-slides.image', $parameters) : null,
            'previewUrl' => route('proposals.groups.custom-slides.preview', $parameters),
            'warning' => $warning,
            'reduced' => $reduced,
        ];
    }

    /**
     * D-431: the warning when something is cut, the note when the content is only reduced.
     *
     * @return array{string|null, string|null}
     */
    private function fit(CustomSlide $slide): array
    {
        try {
            $arrangement = CustomBlocksLayout::arrange($slide, $this->images->sizes($slide), $this->fitter);
        } catch (DocumentToolUnavailable) {
            return [null, null];
        }

        if ($arrangement->overflow) {
            return [Text::get('proposals.custom_slides.overflow'), null];
        }

        return [null, $arrangement->isReduced()
            ? Text::get('proposals.custom_slides.reduced', ['percent' => (string) (int) round($arrangement->scale * 100)])
            : null];
    }
}
