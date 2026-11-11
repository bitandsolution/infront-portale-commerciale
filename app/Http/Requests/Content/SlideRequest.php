<?php

namespace App\Http\Requests\Content;

use App\Domain\Catalog\Services\PriceListGrid;
use App\Domain\Content\Data\SlideData;
use App\Domain\Files\Rules\AcceptableFile;
use App\Domain\Sports\Services\CurrentSeason;
use App\Enums\DocumentLayout;
use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Enums\SlideConditionType;
use App\Enums\SlideDocumentType;
use App\Enums\SlideScope;
use App\Models\Club;
use App\Models\Competition;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Season;
use App\Models\Slide;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * New slide or change of a slide of the library (M08 §4.1, §9; D-115, D-116, D-202, D-402…D-406):
 * title, scope and club, document type, section and order, kind (static or one of the layouts),
 * image, active, conditions. One multipart request with the image (PUT spoofed as POST for a change).
 *
 * - "layout" is "static" or the code of a layout (DocumentLayout).
 * - The club is an Infront club enrolled in a competition of the season (PriceListGrid, D-341).
 * - The image is required unless the slide already has one; the LED simulation has none (D-405).
 * - The recaps belong to one document type: network recap = Infront slide of the network document,
 *   club recap = club slide of the club document.
 * - Conditions: catalogue codes for tier and format, "true" or "false" for the yes / no conditions
 *   (one of the two), tier of an existing competition for the competition (D-202).
 */
class SlideRequest extends FormRequest
{
    public const STATIC = 'static';

    public function authorize(): bool
    {
        $slide = $this->route('slide');

        return $slide instanceof Slide
            ? $this->user()?->can('update', $slide) ?? false
            : $this->user()?->can('create', Slide::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $layouts = array_merge([self::STATIC], array_map(fn (DocumentLayout $layout): string => $layout->value, DocumentLayout::cases()));

        return [
            'title' => ['required', 'string', 'max:120'],
            'scope' => ['required', Rule::enum(SlideScope::class)],
            'club_id' => ['nullable', 'integer'],
            'document_type' => ['required', Rule::enum(SlideDocumentType::class)],
            'slide_section_id' => ['required', 'integer', Rule::exists('slide_sections', 'id')],
            'sort_order' => ['bail', 'required', 'integer', 'between:0,9999'],
            'layout' => ['required', 'string', Rule::in($layouts)],
            'image' => ['nullable', 'bail', 'file', new AcceptableFile(FilePurpose::SlideImage)],
            'is_active' => ['required', 'boolean'],
            'conditions' => ['nullable', 'array:'.implode(',', array_map(fn (SlideConditionType $type): string => $type->value, SlideConditionType::cases()))],
            'conditions.*' => ['array'],
            'conditions.*.*' => ['string', 'max:32'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->checkClub($validator);
            $this->checkLayout($validator);
            $this->checkImage($validator);
            $this->checkConditions($validator);
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = __('content.attributes');
        $names = [];

        foreach (is_array($attributes) ? $attributes : [] as $field => $name) {
            if (is_string($field) && is_string($name)) {
                $names[$field] = $name;
            }
        }

        return $names;
    }

    public function slideData(): SlideData
    {
        $scope = SlideScope::from($this->string('scope')->toString());
        $layout = $this->string('layout')->toString();

        return new SlideData(
            title: $this->string('title')->squish()->toString(),
            scope: $scope,
            clubId: $scope === SlideScope::Club ? $this->integer('club_id') : null,
            documentType: SlideDocumentType::from($this->string('document_type')->toString()),
            sectionId: $this->integer('slide_section_id'),
            sortOrder: $this->integer('sort_order'),
            layout: $layout === self::STATIC ? null : DocumentLayout::from($layout),
            isActive: $this->boolean('is_active'),
            conditions: $this->conditions(),
        );
    }

    public function slideImage(): ?UploadedFile
    {
        $image = $this->file('image');

        return $image instanceof UploadedFile ? $image : null;
    }

    /**
     * The season of the slide: its own for a change, the one of the top bar for a new slide.
     */
    public function season(): ?Season
    {
        $slide = $this->route('slide');

        return $slide instanceof Slide
            ? Season::query()->find($slide->season_id)
            : app(CurrentSeason::class)->get();
    }

    /**
     * @return array<string, list<string>>
     */
    private function conditions(): array
    {
        $conditions = [];
        $input = $this->input('conditions');

        foreach (is_array($input) ? $input : [] as $type => $values) {
            if (! is_string($type) || ! is_array($values)) {
                continue;
            }

            $conditions[$type] = array_values(array_filter($values, fn (mixed $value): bool => is_string($value) && $value !== ''));
        }

        return $conditions;
    }

    private function checkClub(Validator $validator): void
    {
        if ($this->input('scope') !== SlideScope::Club->value) {
            return;
        }

        $club = Club::query()->find($this->integer('club_id'));
        $season = $this->season();

        if ($this->input('club_id') === null || $this->input('club_id') === '') {
            $validator->errors()->add('club_id', Text::get('content.validation.club_required'));
        } elseif ($club === null || $season === null || ! app(PriceListGrid::class)->hasClub($season, $club)) {
            $validator->errors()->add('club_id', Text::get('content.validation.club_not_in_season'));
        }
    }

    private function checkLayout(Validator $validator): void
    {
        $layout = DocumentLayout::tryFrom($this->string('layout')->toString());
        $documentType = $layout?->documentType();

        if ($documentType === null) {
            return;
        }

        $scope = $documentType === DocumentType::Network ? SlideScope::Infront : SlideScope::Club;

        if ($this->input('scope') !== $scope->value || $this->input('document_type') !== $documentType->value) {
            $validator->errors()->add('layout', Text::get($documentType === DocumentType::Network
                ? 'content.validation.recap_network'
                : 'content.validation.recap_club'));
        }
    }

    private function checkImage(Validator $validator): void
    {
        $layout = DocumentLayout::tryFrom($this->string('layout')->toString());

        if ($layout?->needsImage() === false || $this->slideImage() !== null) {
            return;
        }

        $slide = $this->route('slide');

        if (! $slide instanceof Slide || $slide->image_file_id === null) {
            $validator->errors()->add('image', Text::get('content.validation.image_required'));
        }
    }

    private function checkConditions(Validator $validator): void
    {
        foreach ($this->conditions() as $type => $values) {
            $conditionType = SlideConditionType::from($type);
            $allowed = self::allowedValues($conditionType);
            $label = $conditionType->label();

            if ($conditionType->isBoolean() && count(array_unique($values)) > 1) {
                $validator->errors()->add("conditions.{$type}", Text::get('content.validation.condition_boolean', ['condition' => $label]));

                continue;
            }

            if (array_diff($values, $allowed) !== []) {
                $validator->errors()->add("conditions.{$type}", Text::get('content.validation.condition_value', ['condition' => $label]));
            }
        }
    }

    /**
     * Values a condition accepts (D-202): stable codes, never ids.
     *
     * @return list<string>
     */
    public static function allowedValues(SlideConditionType $type): array
    {
        /** @var list<string> $values */
        $values = match ($type) {
            SlideConditionType::PartnershipTier => PartnershipTier::query()->pluck('code')->all(),
            SlideConditionType::LedFormat => LedFormat::query()->pluck('code')->all(),
            SlideConditionType::HasPartnership,
            SlideConditionType::HasLed,
            SlideConditionType::HasHospitality => [SlideConditionType::TRUE, SlideConditionType::FALSE],
            SlideConditionType::CompetitionTier => Competition::query()->distinct()->orderBy('tier')->pluck('tier')
                ->map(fn (mixed $tier): string => is_numeric($tier) ? (string) (int) $tier : '')
                ->filter()
                ->values()
                ->all(),
        };

        return $values;
    }
}
