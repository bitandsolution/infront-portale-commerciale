<?php

namespace App\Http\Requests\Catalog;

use App\Enums\Ability;
use Illuminate\Foundation\Http\FormRequest;

/**
 * On-top text of a price list (M03 F-10; D-335): title and text, both required; the club only when
 * the text is created (none = general).
 */
class OnTopAssetRequest extends FormRequest
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
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:'.BenefitsRequest::MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return CatalogAttributes::names();
    }

    public function titleText(): string
    {
        return $this->string('title')->trim()->toString();
    }

    public function bodyText(): string
    {
        return $this->string('body')->trim()->toString();
    }
}
