<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Data\ClubFacts;
use App\Domain\Content\Data\ComposedSlide;
use App\Domain\Content\Data\Composition;
use App\Domain\Content\Data\CompositionInput;
use App\Domain\Content\Data\SlideInstance;
use App\Domain\Content\Data\VariantFacts;
use App\Enums\DocumentLayout;
use App\Enums\DocumentType;
use App\Enums\SlideConditionType;
use App\Enums\SlideDocumentType;
use App\Enums\SlideScope;
use App\Models\ProposalGroup;
use App\Models\Slide;
use App\Models\SlideSection;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;

/**
 * Composition of the document of a group of variants (M09 §6 step 3, F-02; M08 §4, RB-01…RB-03;
 * D-115, D-118, D-121, D-202, D-396, D-403…D-405): the only implementation, used by the "Documento"
 * step (DM-05), the PDF (DM-04) and the future simulator (M08 F-04).
 *
 * - Type: club when every variant has the same single club, network with two or more distinct clubs
 *   (D-403), none without clubs.
 * - Candidates: active slides of the season of the group, in Italian, of that document type or of
 *   both; the network document uses the Infront slides, the club document the slides of its club
 *   (M09 §3).
 * - Conditions: rows of the same type are alternatives, different types must all be true, no rows =
 *   always included; a condition is true when at least one club of at least one variant satisfies
 *   it (D-118, D-404).
 * - Order: section, order of the slide in the section, then the oldest slide first (M08 RB-03, §10).
 * - Pages: the recap once per variant (D-396), the LED simulation once per club with the LED and not
 *   at all without one (D-405), every other slide once.
 * - Warnings for a missing cover, recap or closing (D-405): the document can still be composed.
 * - Chosen slides (D-397, D-418): every slide is listed with its flag "included" from the
 *   DocumentSelection of the input; cover and recap are always included.
 */
final class DocumentComposer
{
    public function forGroup(ProposalGroup $group): Composition
    {
        return $this->compose(CompositionInput::fromGroup($group));
    }

    public function compose(CompositionInput $input): Composition
    {
        $clubIds = $input->clubIds();
        $type = DocumentType::forClubs(count($clubIds));

        if ($input->season === null) {
            return new Composition($type, $clubIds, [], [Text::get('content.composition.no_season')]);
        }

        if ($type === null) {
            return new Composition(null, [], [], [Text::get('content.composition.no_clubs')]);
        }

        $rows = $input->clubRows();
        $slides = [];

        foreach ($this->candidates($input, $type, $clubIds) as $slide) {
            if (! $this->conditionsHold($slide, $rows)) {
                continue;
            }

            $instances = $this->instances($slide, $input);

            if ($instances === []) {
                continue;
            }

            $mandatory = $slide->layout_code?->isMandatory() ?? false;
            $inSummary = $mandatory || $slide->section->code === SlideSection::CLOSING;

            $slides[] = new ComposedSlide(
                slide: $slide,
                mandatory: $mandatory,
                inSummary: $inSummary,
                instances: $instances,
                competitionTiers: array_map('intval', $slide->conditionValues()[SlideConditionType::CompetitionTier->value] ?? []),
                included: $input->selection->includes($slide->id, $mandatory, $inSummary),
            );
        }

        return new Composition($type, $clubIds, $slides, $this->warnings($slides, $type), $input->selection);
    }

    /**
     * Active candidate slides, already in the order of the document.
     *
     * @param  list<int>  $clubIds
     * @return list<Slide>
     */
    private function candidates(CompositionInput $input, DocumentType $type, array $clubIds): array
    {
        $documentTypes = array_map(
            fn (SlideDocumentType $documentType): string => $documentType->value,
            array_values(array_filter(SlideDocumentType::cases(), fn (SlideDocumentType $documentType): bool => $documentType->fits($type))),
        );

        return array_values(Slide::query()
            ->with(['conditions', 'section'])
            ->where('season_id', $input->season?->id)
            ->where('language', Slide::LANGUAGE)
            ->where('is_active', true)
            ->whereIn('document_type', $documentTypes)
            ->when(
                $type === DocumentType::Network,
                fn (Builder $query) => $query->where('scope', SlideScope::Infront->value),
                fn (Builder $query) => $query->where('scope', SlideScope::Club->value)->where('club_id', $clubIds[0]),
            )
            ->get()
            ->sortBy([
                fn (Slide $a, Slide $b): int => $a->section->sort_order <=> $b->section->sort_order,
                fn (Slide $a, Slide $b): int => $a->sort_order <=> $b->sort_order,
                fn (Slide $a, Slide $b): int => $a->id <=> $b->id,
            ])
            ->all());
    }

    /**
     * @param  list<ClubFacts>  $rows
     */
    private function conditionsHold(Slide $slide, array $rows): bool
    {
        foreach ($slide->conditionValues() as $type => $values) {
            $conditionType = SlideConditionType::from($type);
            $satisfied = false;

            foreach ($rows as $row) {
                if ($row->satisfies($conditionType, $values)) {
                    $satisfied = true;
                    break;
                }
            }

            if (! $satisfied) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<SlideInstance>
     */
    private function instances(Slide $slide, CompositionInput $input): array
    {
        if ($slide->layout_code?->isRecap() === true) {
            return array_map(fn (VariantFacts $variant): SlideInstance => new SlideInstance(variantLetter: $variant->letter), $input->variants);
        }

        if ($slide->layout_code === DocumentLayout::LedMockup) {
            $clubs = [];

            foreach ($input->clubRows() as $row) {
                if ($row->hasLed()) {
                    $clubs[$row->clubId] = true;
                }
            }

            return array_map(fn (int $clubId): SlideInstance => new SlideInstance(clubId: $clubId), array_keys($clubs));
        }

        return [new SlideInstance];
    }

    /**
     * @param  list<ComposedSlide>  $slides
     * @return list<string>
     */
    private function warnings(array $slides, DocumentType $type): array
    {
        $layouts = array_map(fn (ComposedSlide $slide): ?DocumentLayout => $slide->slide->layout_code, $slides);
        $document = mb_strtolower($type->label());
        $warnings = [];

        if (! in_array(DocumentLayout::Cover, $layouts, true)) {
            $warnings[] = Text::get('content.composition.missing_cover', ['document' => $document]);
        }

        $recap = $type === DocumentType::Network ? DocumentLayout::NetworkRecap : DocumentLayout::ClubRecap;

        if (! in_array($recap, $layouts, true)) {
            $warnings[] = Text::get('content.composition.missing_recap', ['document' => $document]);
        }

        $hasClosing = array_filter($slides, fn (ComposedSlide $slide): bool => $slide->slide->section->code === SlideSection::CLOSING) !== [];

        if (! $hasClosing) {
            $warnings[] = Text::get('content.composition.missing_closing', ['document' => $document]);
        }

        return $warnings;
    }
}
