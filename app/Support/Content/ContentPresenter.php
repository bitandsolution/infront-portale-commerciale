<?php

namespace App\Support\Content;

use App\Domain\Catalog\Services\PriceListGrid;
use App\Enums\DocumentLayout;
use App\Enums\SeasonStatus;
use App\Enums\SlideConditionType;
use App\Enums\SlideDocumentType;
use App\Enums\SlideScope;
use App\Http\Requests\Content\SlideRequest;
use App\Models\Club;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Season;
use App\Models\Slide;
use App\Models\SlideSection;
use App\Support\Text;
use Illuminate\Support\Collection;

/**
 * Props of the slide library pages (M08 §4; D-402…D-406): rows of the list with thumbnail and
 * conditions in words, options of the form. Labels from lang/it/content.php.
 */
final class ContentPresenter
{
    public function __construct(private readonly PriceListGrid $grid) {}

    /**
     * @return array{code: string, name: string, closed: bool}
     */
    public static function season(Season $season): array
    {
        return ['code' => $season->code, 'name' => $season->name, 'closed' => $season->status === SeasonStatus::Closed];
    }

    /**
     * Rows of the library of a season, in the order of the documents (section, order, oldest first),
     * with the slides that share section and order with another active slide of the same scope and
     * document (M08 §10).
     *
     * @param  Collection<int, Slide>  $slides
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $slides): array
    {
        $labels = $this->valueLabels();
        $sorted = $slides->sortBy([
            fn (Slide $a, Slide $b): int => $a->section->sort_order <=> $b->section->sort_order,
            fn (Slide $a, Slide $b): int => $a->sort_order <=> $b->sort_order,
            fn (Slide $a, Slide $b): int => $a->id <=> $b->id,
        ])->values();

        return $sorted->map(fn (Slide $slide): array => [
            'id' => $slide->id,
            'title' => $slide->title,
            'scope' => $slide->scope->value,
            'clubId' => $slide->club_id,
            'clubName' => $slide->club?->short_name,
            'documentType' => $slide->document_type->value,
            'documentTypeLabel' => $slide->document_type->label(),
            'sectionId' => $slide->slide_section_id,
            'sectionName' => $slide->section->name,
            'sortOrder' => $slide->sort_order,
            'kindLabel' => self::kindLabel($slide),
            'isDynamic' => $slide->layout_code !== null,
            'imageUrl' => $slide->image_file_id !== null ? route('files.download', $slide->image_file_id) : null,
            'isActive' => $slide->is_active,
            'conditions' => self::conditionLabels($slide->conditionValues(), $labels),
            'duplicateOrder' => $slide->is_active && $sorted->contains(fn (Slide $other): bool => self::sameSlot($slide, $other)),
            'url' => route('content.slides.edit', $slide),
        ])->all();
    }

    /**
     * Values of a slide for the form.
     *
     * @return array<string, mixed>
     */
    public static function form(Slide $slide): array
    {
        return [
            'id' => $slide->id,
            'title' => $slide->title,
            'scope' => $slide->scope->value,
            'clubId' => $slide->club_id,
            'documentType' => $slide->document_type->value,
            'sectionId' => $slide->slide_section_id,
            'sortOrder' => $slide->sort_order,
            'layout' => $slide->layout_code !== null ? $slide->layout_code->value : SlideRequest::STATIC,
            'imageUrl' => $slide->image_file_id !== null ? route('files.download', $slide->image_file_id) : null,
            'isActive' => $slide->is_active,
            'conditions' => (object) $slide->conditionValues(),
        ];
    }

    /**
     * Options of the form: scopes, Infront clubs of the season, document types, sections, kinds of
     * slide and values of the conditions.
     *
     * @return array<string, mixed>
     */
    public function options(Season $season): array
    {
        $labels = $this->valueLabels();
        $conditions = [];

        foreach (SlideConditionType::cases() as $type) {
            $conditions[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'boolean' => $type->isBoolean(),
                'values' => array_map(
                    fn (string $value): array => ['value' => $value, 'label' => $labels[$type->value][$value] ?? $value],
                    SlideRequest::allowedValues($type),
                ),
            ];
        }

        return [
            'scopes' => array_map(fn (SlideScope $scope): array => ['value' => $scope->value, 'label' => $scope->label()], SlideScope::cases()),
            'clubs' => $this->grid->clubs($season)
                ->map(fn (Club $club): array => ['value' => (string) $club->id, 'label' => $club->name])
                ->values()
                ->all(),
            'documentTypes' => array_map(fn (SlideDocumentType $type): array => ['value' => $type->value, 'label' => $type->label()], SlideDocumentType::cases()),
            'sections' => SlideSection::query()->orderBy('sort_order')->get()
                ->map(fn (SlideSection $section): array => ['value' => (string) $section->id, 'label' => $section->name])
                ->values()
                ->all(),
            'closingSection' => (string) SlideSection::query()->where('code', SlideSection::CLOSING)->value('id'),
            'layouts' => array_merge(
                [['value' => SlideRequest::STATIC, 'label' => Text::get('content.kinds.static')]],
                array_map(fn (DocumentLayout $layout): array => [
                    'value' => $layout->value,
                    'label' => Text::get('content.kinds.dynamic').' · '.$layout->label(),
                ], DocumentLayout::cases()),
            ),
            'imageLess' => array_values(array_map(
                fn (DocumentLayout $layout): string => $layout->value,
                array_filter(DocumentLayout::cases(), fn (DocumentLayout $layout): bool => ! $layout->needsImage()),
            )),
            'conditions' => $conditions,
        ];
    }

    /**
     * "Statica" or "Dinamica · Copertina".
     */
    public static function kindLabel(Slide $slide): string
    {
        return $slide->layout_code === null
            ? $slide->kind->label()
            : $slide->kind->label().' · '.$slide->layout_code->label();
    }

    /**
     * Conditions in words, one per type: "Livello partnership = Top o Premium".
     *
     * @param  array<string, list<string>>  $values
     * @param  array<string, array<string, string>>  $labels
     * @return list<string>
     */
    public static function conditionLabels(array $values, array $labels): array
    {
        $texts = [];

        foreach ($values as $type => $typeValues) {
            $names = array_map(fn (string $value): string => $labels[$type][$value] ?? $value, $typeValues);
            $texts[] = SlideConditionType::from($type)->label().' = '.implode(' o ', $names);
        }

        return $texts;
    }

    /**
     * Readable names of the values of the conditions, by type.
     *
     * @return array<string, array<string, string>>
     */
    public function valueLabels(): array
    {
        $yesNo = [SlideConditionType::TRUE => Text::get('content.conditions.yes'), SlideConditionType::FALSE => Text::get('content.conditions.no')];
        $tiers = __('content.conditions.competition_tiers');

        return [
            SlideConditionType::PartnershipTier->value => self::names(PartnershipTier::query()->orderBy('sort_order')->get()),
            SlideConditionType::HasPartnership->value => $yesNo,
            SlideConditionType::LedFormat->value => self::names(LedFormat::query()->orderBy('sort_order')->get()),
            SlideConditionType::HasLed->value => $yesNo,
            SlideConditionType::HasHospitality->value => $yesNo,
            SlideConditionType::CompetitionTier->value => array_filter(is_array($tiers) ? $tiers : [], 'is_string'),
        ];
    }

    /**
     * @param  iterable<PartnershipTier|LedFormat>  $values
     * @return array<string, string>
     */
    private static function names(iterable $values): array
    {
        $names = [];

        foreach ($values as $value) {
            $names[$value->code] = $value->name;
        }

        return $names;
    }

    /**
     * Two active slides of the same scope, club and section with the same order, whose document
     * types overlap (M08 §10: ordered by upload, with a warning).
     */
    private static function sameSlot(Slide $slide, Slide $other): bool
    {
        return $other->id !== $slide->id
            && $other->is_active
            && $other->scope === $slide->scope
            && $other->club_id === $slide->club_id
            && $other->slide_section_id === $slide->slide_section_id
            && $other->sort_order === $slide->sort_order
            && ($other->document_type === $slide->document_type
                || $other->document_type === SlideDocumentType::Both
                || $slide->document_type === SlideDocumentType::Both);
    }
}
