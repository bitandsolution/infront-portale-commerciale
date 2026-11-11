<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Data\SlideData;
use App\Enums\SlideConditionType;
use App\Models\Slide;

/**
 * Conditions of a slide (D-202): one row per value, replaced together inside the transaction of the
 * Action that saves the slide.
 */
final class SlideConditions
{
    /**
     * Values by type of the data, in the order of SlideConditionType, without empty types.
     *
     * @return array<string, list<string>>
     */
    public static function of(SlideData $data): array
    {
        $conditions = [];

        foreach (SlideConditionType::cases() as $type) {
            $values = $data->valuesOf($type);

            if ($values !== []) {
                $conditions[$type->value] = $values;
            }
        }

        return $conditions;
    }

    /**
     * Replaces the rows of the slide.
     *
     * @return array<string, list<string>> the values written
     */
    public static function write(Slide $slide, SlideData $data): array
    {
        $conditions = self::of($data);

        $slide->conditions()->delete();

        foreach ($conditions as $type => $values) {
            foreach ($values as $value) {
                $slide->conditions()->create(['condition_type' => SlideConditionType::from($type), 'value' => $value]);
            }
        }

        $slide->unsetRelation('conditions');

        return $conditions;
    }
}
