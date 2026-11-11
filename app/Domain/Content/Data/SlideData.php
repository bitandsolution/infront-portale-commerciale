<?php

namespace App\Domain\Content\Data;

use App\Enums\DocumentLayout;
use App\Enums\SlideConditionType;
use App\Enums\SlideDocumentType;
use App\Enums\SlideKind;
use App\Enums\SlideScope;

/**
 * Data of a slide from the library page (M08 §4.1; D-402…D-406): the season is the one of the slide
 * (from the top bar when it is created) and never changes.
 */
final readonly class SlideData
{
    /**
     * @param  array<string, list<string>>  $conditions  values by condition type (SlideConditionType)
     */
    public function __construct(
        public string $title,
        public SlideScope $scope,
        public ?int $clubId,
        public SlideDocumentType $documentType,
        public int $sectionId,
        public int $sortOrder,
        public ?DocumentLayout $layout,
        public bool $isActive,
        public array $conditions,
    ) {}

    public function kind(): SlideKind
    {
        return $this->layout === null ? SlideKind::Static : SlideKind::Dynamic;
    }

    /**
     * Values of one condition type, sorted and without duplicates.
     *
     * @return list<string>
     */
    public function valuesOf(SlideConditionType $type): array
    {
        $values = array_values(array_unique($this->conditions[$type->value] ?? []));
        sort($values);

        return $values;
    }
}
