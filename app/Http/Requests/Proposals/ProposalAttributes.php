<?php

namespace App\Http\Requests\Proposals;

/**
 * Names of the fields of the configurator in the validation messages (lang/it/proposals.php).
 */
final class ProposalAttributes
{
    /**
     * @return array<string, string>
     */
    public static function names(): array
    {
        $attributes = __('proposals.attributes');
        $names = [];

        foreach (is_array($attributes) ? $attributes : [] as $field => $name) {
            if (is_string($field) && is_string($name)) {
                $names[$field] = $name;
            }
        }

        return $names;
    }
}
