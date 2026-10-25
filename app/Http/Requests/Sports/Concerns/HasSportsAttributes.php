<?php

namespace App\Http\Requests\Sports\Concerns;

/**
 * Italian names of the fields of the sports registry in the validation messages
 * (lang/it/sports.php "attributes"): "code" is the code of a season or a competition here, not the
 * code of the second factor of lang/it/validation.php.
 */
trait HasSportsAttributes
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = __('sports.attributes');

        if (! is_array($attributes)) {
            return [];
        }

        $names = [];

        foreach ($attributes as $field => $name) {
            if (is_string($field) && is_string($name)) {
                $names[$field] = $name;
            }
        }

        return $names;
    }
}
