<?php

namespace App\Support\Content;

use App\Domain\Content\Data\ComposedSlide;
use App\Domain\Content\Data\Composition;
use App\Domain\Content\Data\ExcludedSlide;
use App\Domain\Content\Data\SimulatedClub;
use App\Domain\Content\Services\CompositionSimulator;
use App\Enums\DocumentLayout;
use App\Enums\DocumentMode;
use App\Enums\SeasonStatus;
use App\Models\Club;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Season;
use App\Models\Slide;
use App\Support\Text;

/**
 * Page "Contenuti › Simulatore" (M08 F-04, FL-01, CA5; D-494…D-498): the options of the form (clubs
 * of the season with their championship, packages, formats, modes), the choices read from the address
 * and the result of the composition: document type, slides in order by section with thumbnail and
 * conditions, pages, warnings, slides out of "Solo riepilogo" and the slides excluded by a condition
 * not satisfied (D-495). Everything comes from DocumentComposer; the page computes nothing.
 */
final class SimulatorPresenter
{
    /** @var array<string, array<string, string>>|null */
    private ?array $valueLabels = null;

    public function __construct(
        private readonly CompositionSimulator $simulator,
        private readonly ContentPresenter $content,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function season(Season $season): array
    {
        return ['id' => $season->id, 'code' => $season->code, 'closed' => $season->status === SeasonStatus::Closed];
    }

    /**
     * @return array<string, mixed>
     */
    public function options(Season $season): array
    {
        return [
            'clubs' => $this->simulator->clubs($season)
                ->map(fn (Club $club): array => [
                    'id' => $club->id,
                    'name' => $club->name,
                    'competition' => CompositionSimulator::competitionOf($club)?->name,
                ])
                ->values()
                ->all(),
            'tiers' => $this->simulator->tiers()
                ->map(fn (PartnershipTier $tier): array => ['code' => $tier->code, 'name' => $tier->name])
                ->values()
                ->all(),
            'formats' => $this->simulator->formats()
                ->map(fn (LedFormat $format): array => ['code' => $format->code, 'name' => $format->name])
                ->values()
                ->all(),
            'modes' => array_map(fn (DocumentMode $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
                'description' => Text::get('ui.proposals.slides.mode_hints.'.$mode->value),
            ], DocumentMode::cases()),
        ];
    }

    /**
     * The choices as the form shows them: only the clubs of the season, each once, and only packages
     * and formats of the catalogue (the others count as none, as in CompositionSimulator).
     *
     * @param  list<SimulatedClub>  $choices
     * @return array{clubs: list<array{id: int, tier: string|null, format: string|null, hospitality: bool}>, mode: string}
     */
    public function choice(Season $season, array $choices, DocumentMode $mode): array
    {
        $ids = $this->simulator->clubs($season)->modelKeys();
        /** @var list<string> $tiers */
        $tiers = $this->simulator->tiers()->pluck('code')->all();
        /** @var list<string> $formats */
        $formats = $this->simulator->formats()->pluck('code')->all();
        $clubs = [];

        foreach ($choices as $choice) {
            if (in_array($choice->clubId, $ids, true)) {
                $clubs[] = [
                    'id' => $choice->clubId,
                    'tier' => in_array($choice->tierCode, $tiers, true) ? $choice->tierCode : null,
                    'format' => in_array($choice->formatCode, $formats, true) ? $choice->formatCode : null,
                    'hospitality' => $choice->hospitality,
                ];
            }
        }

        return ['clubs' => $clubs, 'mode' => $mode->value];
    }

    /**
     * @return array<string, mixed>
     */
    public function result(Composition $composition): array
    {
        $sections = [];

        foreach ($composition->slides as $composed) {
            $name = $composed->section()->name;
            $last = array_key_last($sections);

            if ($last === null || $sections[$last]['name'] !== $name) {
                $sections[] = ['name' => $name, 'slides' => []];
                $last = array_key_last($sections);
            }

            $sections[$last]['slides'][] = $this->slide($composed);
        }

        return [
            'documentLabel' => $composition->documentType?->label(),
            'includedSlides' => count($composition->includedSlides()),
            'totalSlides' => count($composition->slides),
            'pages' => $composition->pageCount(),
            'warnings' => $composition->warnings,
            'sections' => $sections,
            'excluded' => array_map(fn (ExcludedSlide $excluded): array => $this->excluded($excluded), $composition->excluded),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function slide(ComposedSlide $composed): array
    {
        $slide = $composed->slide;
        $kind = $this->kind($slide);
        $pages = $composed->pageCount();

        return [
            'id' => $slide->id,
            'title' => $slide->title,
            'kind' => $kind,
            'note' => $kind !== 'static' ? Text::get('ui.proposals.slides.kinds.'.$kind) : null,
            'conditions' => $this->conditions($slide->conditionValues()),
            'mandatory' => $composed->mandatory,
            'included' => $composed->included,
            'pages' => $composed->included ? $pages : 0,
            'pagesLabel' => match (true) {
                ! $composed->included => null,
                $kind === 'led' && $pages === 0 => Text::get('ui.proposals.slides.led_not_configured'),
                $kind === 'led' => Text::get($pages === 1 ? 'ui.proposals.slides.page_per_club_one' : 'ui.proposals.slides.pages_per_club', ['count' => (string) $pages]),
                default => null,
            },
            'thumbnailUrl' => $kind !== 'led' && $slide->image_file_id !== null ? route('files.thumbnail', $slide->image_file_id) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function excluded(ExcludedSlide $excluded): array
    {
        $slide = $excluded->slide;

        return [
            'id' => $slide->id,
            'title' => $slide->title,
            'section' => $slide->section->name,
            'reasons' => $excluded->withoutLed
                ? [Text::get('ui.content.simulator.without_led')]
                : array_map(
                    fn (string $condition): string => Text::get('ui.content.simulator.requires', ['condition' => $condition]),
                    $this->conditions($excluded->unmetConditions),
                ),
            'thumbnailUrl' => $this->kind($slide) !== 'led' && $slide->image_file_id !== null ? route('files.thumbnail', $slide->image_file_id) : null,
        ];
    }

    private function kind(Slide $slide): string
    {
        return match ($slide->layout_code) {
            null => 'static',
            DocumentLayout::Cover => 'cover',
            DocumentLayout::NetworkRecap, DocumentLayout::ClubRecap => 'recap',
            DocumentLayout::LedMockup => 'led',
            DocumentLayout::CustomBlocks => 'custom',
        };
    }

    /**
     * "Livello partnership = Top o Premium", one per condition type.
     *
     * @param  array<string, list<string>>  $values
     * @return list<string>
     */
    private function conditions(array $values): array
    {
        $this->valueLabels ??= $this->content->valueLabels();

        return ContentPresenter::conditionLabels($values, $this->valueLabels);
    }
}
