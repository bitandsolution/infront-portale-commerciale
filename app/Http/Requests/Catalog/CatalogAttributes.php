<?php

namespace App\Http\Requests\Catalog;

/**
 * Names of the fields of the catalogue in the validation messages (lang/it/catalog.php).
 */
final class CatalogAttributes
{
    /**
     * @return array<string, string>
     */
    public static function names(): array
    {
        $attributes = __('catalog.attributes');

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
