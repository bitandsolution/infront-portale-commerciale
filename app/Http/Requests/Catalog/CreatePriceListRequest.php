<?php

namespace App\Http\Requests\Catalog;

use App\Enums\Ability;
use Illuminate\Foundation\Http\FormRequest;

/**
 * New price list of a season, empty in draft (D-333). The season is the one shown by the page,
 * sent explicitly, so a season changed in another tab never gets a price list by mistake.
 */
class CreatePriceListRequest extends FormRequest
{
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
            'season_id' => ['bail', 'required', 'integer', 'exists:seasons,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return CatalogAttributes::names();
    }
}
