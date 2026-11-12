<?php

namespace App\Support\Proposals;

use App\Domain\Content\Data\ComposedSlide;
use App\Domain\Content\Data\Composition;
use App\Domain\Content\Data\CompositionInput;
use App\Domain\Content\Services\DocumentComposer;
use App\Domain\Content\Services\LedMockups;
use App\Domain\Proposals\Actions\SaveDocumentSlides;
use App\Enums\DocumentLayout;
use App\Enums\DocumentMode;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\ProposalVersion;
use App\Models\User;
use App\Support\Text;

/**
 * The slides of the document of a group for the step "Documento" of the configurator and for the
 * detail of the group (D-397, D-418, D-419; DM-05), from DocumentComposer (one implementation of the
 * composition):
 *
 * - while the group is worked on: the composition of the working versions with the choice of the
 *   group, editable by the agent on drafts and rejected groups, by admin and manager while it waits;
 * - once approved: the composition of the approved variants with the choice frozen on their
 *   versions, read only (the same the PDF uses).
 *
 * Every slide whose conditions hold is listed by section, with its thumbnail (D-420), whether it is
 * mandatory, excluded, out of the "Solo riepilogo" and how many pages it makes. The warnings for the
 * missing system slides reach admin and manager (D-416); the agent reads a neutral note.
 *
 * DM-07: the slides composed with blocks are listed in their section with their own tick, and the
 * editor of their content comes with the list ("custom", CustomSlidesPresenter).
 */
final class DocumentSlidesPresenter
{
    public function __construct(
        private readonly DocumentComposer $composer,
        private readonly LedMockups $ledMockups,
        private readonly CustomSlidesPresenter $customSlides,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function forGroup(ProposalGroup $group, User $viewer): ?array
    {
        $variants = $group->proposals()->with(['currentVersion'])->get();

        if ($variants->isEmpty()) {
            return null;
        }

        $approved = $variants->filter(fn (Proposal $variant): bool => $variant->status === ProposalStatus::Approved && $variant->currentVersion !== null);
        $frozen = $approved->isNotEmpty();

        if ($frozen) {
            /** @var array<string, ProposalVersion> $versions */
            $versions = [];

            foreach ($approved as $variant) {
                /** @var ProposalVersion $version */
                $version = $variant->currentVersion;
                $versions[(string) $variant->variant_letter] = $version;
            }

            $input = CompositionInput::fromVersions($group->season, $versions);
        } else {
            $input = CompositionInput::fromGroup($group);
        }

        $composition = $this->composer->compose($input);

        $editable = ! $frozen && SaveDocumentSlides::editableBy($viewer, $group, $variants->all());
        $isReviewer = $viewer->can('review', ProposalGroup::class);
        $variantCount = $frozen ? $approved->count() : $variants->count();

        return [
            'mode' => $composition->selection->mode->value,
            'modes' => array_map(fn (DocumentMode $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
                'description' => Text::get('ui.proposals.slides.mode_hints.'.$mode->value),
            ], DocumentMode::cases()),
            'documentLabel' => $composition->documentType?->label(),
            'editable' => $editable,
            'frozen' => $frozen,
            'saveUrl' => route('proposals.groups.slides', $group),
            'sections' => $this->sections($composition, $variantCount),
            'includedSlides' => count($composition->includedSlides()),
            'totalSlides' => count($composition->slides),
            'pages' => $composition->pageCount(),
            'warnings' => $isReviewer ? $composition->warnings : [],
            'note' => ! $isReviewer && $composition->documentType !== null && $composition->warnings !== [] ? Text::get('ui.proposals.slides.agent_note') : null,
            'custom' => $this->customSlides->present($group, $input->customSlides, $composition, $editable),
        ];
    }

    /**
     * @return list<array{name: string, slides: list<array<string, mixed>>}>
     */
    private function sections(Composition $composition, int $variantCount): array
    {
        $sections = [];

        foreach ($composition->slides as $composed) {
            $name = $composed->section()->name;
            $last = array_key_last($sections);

            if ($last === null || $sections[$last]['name'] !== $name) {
                $sections[] = ['name' => $name, 'slides' => []];
                $last = array_key_last($sections);
            }

            $sections[$last]['slides'][] = $this->slide($composed, $composition, $variantCount);
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>
     */
    private function slide(ComposedSlide $composed, Composition $composition, int $variantCount): array
    {
        $slide = $composed->slide;

        if ($composed->custom !== null) {
            return [
                'id' => $composed->custom->id,
                'custom' => true,
                'title' => $composed->custom->title,
                'kind' => 'custom',
                'note' => Text::get('ui.proposals.slides.kinds.custom'),
                'mandatory' => false,
                'inSummary' => false,
                'included' => $composed->included,
                'excluded' => ! $composed->custom->included,
                'locked' => $composition->selection->mode === DocumentMode::Summary,
                'pages' => 1,
                'pagesLabel' => null,
                'thumbnailUrl' => $slide->image_file_id !== null ? route('files.thumbnail', $slide->image_file_id) : null,
            ];
        }

        $kind = match ($slide->layout_code) {
            null => 'static',
            DocumentLayout::Cover => 'cover',
            DocumentLayout::NetworkRecap, DocumentLayout::ClubRecap => 'recap',
            DocumentLayout::LedMockup => 'led',
            DocumentLayout::CustomBlocks => 'custom',
        };
        $pages = count($composed->instances);
        $summaryOnly = $composition->selection->mode === DocumentMode::Summary;

        return [
            'id' => $slide->id,
            'custom' => false,
            'title' => $slide->title,
            'kind' => $kind,
            'note' => $kind !== 'static' ? Text::get('ui.proposals.slides.kinds.'.$kind) : null,
            'mandatory' => $composed->mandatory,
            'inSummary' => $composed->inSummary,
            'included' => $composed->included,
            'excluded' => $composition->selection->excludes($slide->id),
            // The tick cannot change: cover and recap always, the others out of "Solo riepilogo".
            'locked' => $composed->mandatory || ($summaryOnly && ! $composed->inSummary),
            'pages' => $pages,
            'pagesLabel' => match (true) {
                $kind === 'recap' && $variantCount > 1 => Text::get('ui.proposals.slides.pages_per_variant', ['count' => (string) $pages]),
                $kind === 'led' => Text::get($pages === 1 ? 'ui.proposals.slides.page_per_club_one' : 'ui.proposals.slides.pages_per_club', ['count' => (string) $pages]),
                default => null,
            },
            'thumbnailUrl' => $kind === 'led' ? $this->ledThumbnail($composed) : ($slide->image_file_id !== null ? route('files.thumbnail', $slide->image_file_id) : null),
        ];
    }

    /**
     * D-426: the photo of the field of the first club with the LED, without the logo.
     */
    private function ledThumbnail(ComposedSlide $composed): ?string
    {
        foreach ($composed->instances as $instance) {
            if ($instance->clubId !== null && $instance->formatCode !== null) {
                $setting = $this->ledMockups->settingFor($instance->clubId, $instance->formatCode);

                if ($setting !== null) {
                    return route('files.thumbnail', $setting->photo_file_id);
                }
            }
        }

        return null;
    }
}
