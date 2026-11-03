<?php

namespace App\Http\Requests\Catalog;

use App\Enums\Ability;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Benefit texts of a club and tier, one per category (M03 F-08; D-335): plain text with line
 * breaks, optional; an empty text removes the benefit.
 */
class BenefitsRequest extends FormRequest
{
    public const MAX_LENGTH = 5000;

    public function authorize(): bool
    {
        return $this->user()?->can(Ability::CatalogManage->value) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'partnership_tier_id' => ['bail', 'required', 'integer', 'exists:partnership_tiers,id'],
            'texts' => ['present', 'array'],
            'texts.*' => ['nullable', 'string', 'max:'.self::MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return CatalogAttributes::names();
    }

    /**
     * Benefit category id → text.
     *
     * @return array<int, string|null>
     */
    public function texts(): array
    {
        $texts = [];

        foreach ((array) $this->input('texts', []) as $category => $text) {
            if (is_numeric($category)) {
                $texts[(int) $category] = is_string($text) ? $text : null;
            }
        }

        return $texts;
    }
}
