<?php

namespace App\Models;

use App\Enums\DocumentLayout;
use App\Enums\SlideConditionType;
use App\Enums\SlideDocumentType;
use App\Enums\SlideKind;
use App\Enums\SlideScope;
use Database\Factories\SlideFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Slide of the library (M08 §4; D-115, D-116, D-202, D-402…D-406): Infront or club scope, season,
 * document type, section and order, static or dynamic with its layout, PNG 1920×1080 (none for the
 * LED simulation), inclusion conditions. Deactivated, never deleted (C4, D-406); a new image is a new
 * file (C10).
 *
 * @property int $id
 * @property string $title
 * @property SlideScope $scope
 * @property int|null $club_id
 * @property int $season_id
 * @property SlideDocumentType $document_type
 * @property int $slide_section_id
 * @property int $sort_order
 * @property SlideKind $kind
 * @property int|null $image_file_id
 * @property DocumentLayout|null $layout_code
 * @property array<string, mixed>|null $layout_options
 * @property string $language
 * @property bool $is_active
 * @property int|null $copied_from_id
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Club|null $club
 * @property-read Season $season
 * @property-read SlideSection $section
 * @property-read Collection<int, SlideCondition> $conditions
 */
final class Slide extends Model
{
    /** @use HasFactory<SlideFactory> */
    use HasFactory;

    /** Only Italian in the 1.0 (D-124). */
    public const LANGUAGE = 'it';

    protected $guarded = ['id'];

    /**
     * Defaults of the columns, also before the first save (audit of a new slide).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'language' => self::LANGUAGE,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => SlideScope::class,
            'document_type' => SlideDocumentType::class,
            'kind' => SlideKind::class,
            'layout_code' => DocumentLayout::class,
            'layout_options' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Values of the conditions by type, in a stable order: e.g. ['partnership_tier' => ['PREMIUM',
     * 'TOP']].
     *
     * @return array<string, list<string>>
     */
    public function conditionValues(): array
    {
        $values = [];

        foreach (SlideConditionType::cases() as $type) {
            /** @var list<string> $typeValues */
            $typeValues = $this->conditions
                ->filter(fn (SlideCondition $condition): bool => $condition->condition_type === $type)
                ->map(fn (SlideCondition $condition): string => $condition->value)
                ->sort()
                ->values()
                ->all();

            if ($typeValues !== []) {
                $values[$type->value] = $typeValues;
            }
        }

        return $values;
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return BelongsTo<SlideSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SlideSection::class, 'slide_section_id');
    }

    /**
     * @return HasMany<SlideCondition, $this>
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(SlideCondition::class);
    }
}
