<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Data\ClubFacts;
use App\Domain\Content\Data\ComposedSlide;
use App\Domain\Content\Data\Composition;
use App\Domain\Content\Data\CompositionInput;
use App\Domain\Content\Data\CustomSlide;
use App\Domain\Content\Data\ExcludedSlide;
use App\Domain\Content\Data\SlideInstance;
use App\Domain\Content\Data\VariantFacts;
use App\Enums\DocumentLayout;
use App\Enums\DocumentType;
use App\Enums\SlideConditionType;
use App\Enums\SlideDocumentType;
use App\Enums\SlideScope;
use App\Models\Club;
use App\Models\ProposalGroup;
use App\Models\Slide;
use App\Models\SlideSection;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;

/**
 * Composition of the document of a group of variants (M09 §6 step 3, F-02; M08 §4, RB-01…RB-03;
 * D-115, D-118, D-121, D-202, D-396, D-403…D-405): the only implementation, used by the "Documento"
 * step (DM-05), the PDF (DM-04) and the simulator (M08 F-04, F6-02: CompositionSimulator).
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
 * - Pages: the recap once per variant (D-396), the LED simulation once per pair of club and format
 *   with the LED in the group and not at all without one (D-405, D-423), every other slide once.
 * - A pair of club and format without configuration of the LED simulation (club or generic) gives a
 *   warning: that page is skipped by the document (D-427).
 * - Warnings for a missing cover, recap or closing (D-405): the document can still be composed.
 * - Excluded slides (M08 F-04, D-494, D-495): a candidate whose conditions do not hold is listed apart with
 *   the condition types not satisfied, and the LED simulation without any LED in the group with its
 *   own reason, for the simulator; the step "Documento" and the PDF do not read them.
 * - Pages (D-427, D-499): a page of the LED simulation without configuration is not drawable, so it is
 *   not counted (the PDF skips it).
 * - Chosen slides (D-397, D-418): every slide is listed with its flag "included" from the
 *   DocumentSelection of the input; cover and recap are always included.
 * - Slides composed with blocks (D-399, D-428, D-429; DM-07): the first library slide with the
 *   layout "custom_blocks" (the background "Slide personalizzata") is never a page by itself; every
 *   composed slide of the group is drawn on it and placed at the end of its section, in the order the
 *   slides were added. Without that background the composed slides are left out, with a warning.
 * - KPI (F6-03; D-500…D-503): the KPI in force of the clubs of the group (ClubKpis) and the clubs
 *   without some of the KPI the document type uses, listed apart from the warnings; they never block.
 */
final class DocumentComposer
{
    public function __construct(
        private readonly LedMockups $ledMockups,
        private readonly ClubKpis $kpis,
    ) {}

    public function forGroup(ProposalGroup $group): Composition
    {
        return $this->compose(CompositionInput::fromGroup($group));
    }

    public function compose(CompositionInput $input): Composition
    {
        $clubIds = $input->clubIds();
        $type = DocumentType::forClubs(count($clubIds));

        $season = $input->season;

        if ($season === null) {
            return new Composition($type, $clubIds, [], [Text::get('content.composition.no_season')]);
        }

        if ($type === null) {
            return new Composition(null, [], [], [Text::get('content.composition.no_clubs')]);
        }

        $rows = $input->clubRows();
        $slides = [];
        $excluded = [];
        $background = null;

        foreach ($this->candidates($input, $type, $clubIds) as $slide) {
            $isBackground = $slide->layout_code?->isCustomBackground() === true;
            $unmet = $this->unmetConditions($slide, $rows);

            if ($unmet !== []) {
                if (! $isBackground) {
                    $excluded[] = new ExcludedSlide($slide, $unmet);
                }

                continue;
            }

            if ($isBackground) {
                $background ??= $slide;

                continue;
            }

            $instances = $this->instances($slide, $input);

            if ($instances === []) {
                $excluded[] = new ExcludedSlide($slide, [], withoutLed: true);

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

        $warnings = [...$this->warnings($slides, $type), ...$this->ledWarnings($slides)];

        if ($input->customSlides !== []) {
            if ($background === null) {
                $warnings[] = Text::get('content.composition.missing_custom_background', ['document' => mb_strtolower($type->label())]);
            } else {
                $slides = $this->withCustomSlides($slides, $input, $background);
            }
        }

        $kpis = $this->kpis->of($season, self::clubTiers($input));

        return new Composition($type, $clubIds, $slides, $warnings, $input->selection, $excluded, $kpis, $kpis->missing($type));
    }

    /**
     * Clubs of the group with the tier of their competition (D-251), in the order they first appear;
     * the first row with a competition gives it.
     *
     * @return array<int, int|null>
     */
    private static function clubTiers(CompositionInput $input): array
    {
        $tiers = [];

        foreach ($input->clubRows() as $row) {
            if (! array_key_exists($row->clubId, $tiers) || $tiers[$row->clubId] === null) {
                $tiers[$row->clubId] = $row->competitionTier;
            }
        }

        return $tiers;
    }

    /**
     * The slides composed with blocks placed at the end of their section (D-429), on the background
     * of the library (D-428).
     *
     * @param  list<ComposedSlide>  $slides
     * @return list<ComposedSlide>
     */
    private function withCustomSlides(array $slides, CompositionInput $input, Slide $background): array
    {
        /** @var array<string, SlideSection> $sections */
        $sections = SlideSection::query()->get()->keyBy('code')->all();
        $custom = $input->customSlides;
        usort($custom, fn (CustomSlide $a, CustomSlide $b): int => [$a->sortOrder, $a->id] <=> [$b->sortOrder, $b->id]);

        foreach ($custom as $slide) {
            $section = $sections[$slide->sectionCode] ?? $sections[SlideSection::BRAND_EXPOSURE] ?? null;

            if ($section === null) {
                continue;
            }

            $position = 0;

            foreach ($slides as $index => $composed) {
                if ($composed->section()->sort_order <= $section->sort_order) {
                    $position = $index + 1;
                }
            }

            array_splice($slides, $position, 0, [new ComposedSlide(
                slide: $background,
                mandatory: false,
                inSummary: false,
                instances: [new SlideInstance],
                competitionTiers: [],
                included: $input->selection->includesCustom($slide->included),
                custom: $slide,
                customSection: $section,
            )]);
        }

        return $slides;
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
     * The condition types of the slide that no club of any variant satisfies, with their values; an
     * empty list means the slide enters the document (RB-01, D-118, D-404).
     *
     * @param  list<ClubFacts>  $rows
     * @return array<string, list<string>>
     */
    private function unmetConditions(Slide $slide, array $rows): array
    {
        $unmet = [];

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
                $unmet[$type] = $values;
            }
        }

        return $unmet;
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
            $pairs = [];

            foreach ($input->clubRows() as $row) {
                if ($row->formatCode !== null && ! isset($pairs[$row->clubId.'|'.$row->formatCode])) {
                    $pairs[$row->clubId.'|'.$row->formatCode] = new SlideInstance(
                        clubId: $row->clubId,
                        formatCode: $row->formatCode,
                        drawable: $this->ledMockups->settingFor($row->clubId, $row->formatCode) !== null,
                    );
                }
            }

            return array_values($pairs);
        }

        return [new SlideInstance];
    }

    /**
     * D-427: pairs of club and format of the LED simulation without a configuration.
     *
     * @param  list<ComposedSlide>  $slides
     * @return list<string>
     */
    private function ledWarnings(array $slides): array
    {
        $missing = [];

        foreach ($slides as $composed) {
            if (! $composed->included) {
                continue;
            }

            foreach ($composed->instances as $instance) {
                if ($instance->clubId === null || $instance->formatCode === null) {
                    continue;
                }

                if (! $instance->drawable) {
                    $missing[$instance->clubId.'|'.$instance->formatCode] = $instance;
                }
            }
        }

        if ($missing === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = Club::query()->whereKey(array_map(fn (SlideInstance $instance): ?int => $instance->clubId, $missing))->pluck('name', 'id')->all();

        return array_values(array_map(
            fn (SlideInstance $instance): string => $this->ledMockups->missingWarning((string) ($names[$instance->clubId] ?? $instance->clubId), (string) $instance->formatCode),
            $missing,
        ));
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
