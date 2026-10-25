<?php

namespace App\Http\Requests\Settings;

use App\Enums\SettingKey;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Settings page (PR-07): one whole number per editable key, inside its allowed range
 * (SettingKey::range). The fixed keys are not part of the form and are never validated, so a
 * value sent for them does not reach the Action.
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (SettingKey::editable() as $key) {
            [$min, $max] = $key->range() ?? [0, 0];
            $rules[$key->value] = ['required', 'integer', 'between:'.$min.','.$max];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (SettingKey::editable() as $key) {
            $attributes[$key->value] = $key->label();
        }

        return $attributes;
    }

    /**
     * New values by key, as integers.
     *
     * @return array<string, int>
     */
    public function values(): array
    {
        $values = [];

        foreach (SettingKey::editable() as $key) {
            $values[$key->value] = $this->integer($key->value);
        }

        return $values;
    }
}
