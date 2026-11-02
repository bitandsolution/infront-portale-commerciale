<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Data\CatalogValueData;
use App\Enums\Ability;
use App\Enums\CatalogValueKind;
use App\Models\BenefitCategory;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * Change of a value of the catalogue (D-331): name, order and, where the kind has them,
 * description (LED formats) and active (all but the benefit categories). The code is never sent:
 * it comes from the system data (D-332).
 */
class CatalogValueRequest extends FormRequest
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
        $kind = $this->kind();

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['bail', 'required', 'integer', 'between:0,9999'],
        ];

        if ($kind->hasDescription()) {
            $rules['description'] = ['nullable', 'string', 'max:500'];
        }

        if ($kind->hasActiveFlag()) {
            $rules['is_active'] = ['required', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
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

    public function catalogValueData(): CatalogValueData
    {
        $kind = $this->kind();
        $description = $kind->hasDescription() ? $this->string('description')->trim()->toString() : null;

        return new CatalogValueData(
            name: $this->string('name')->trim()->toString(),
            description: $description === '' ? null : $description,
            sortOrder: $this->integer('sort_order'),
            isActive: $kind->hasActiveFlag() ? $this->boolean('is_active') : null,
        );
    }

    /**
     * The kind follows the value bound to the route (catalog.values.<segment>.update).
     */
    public function catalogValue(): LedFormat|PartnershipTier|HospitalityVariant|BenefitCategory
    {
        foreach (['ledFormat', 'partnershipTier', 'hospitalityVariant', 'benefitCategory'] as $parameter) {
            $value = $this->route($parameter);

            if ($value instanceof LedFormat || $value instanceof PartnershipTier
                || $value instanceof HospitalityVariant || $value instanceof BenefitCategory) {
                return $value;
            }
        }

        throw new LogicException('CatalogValueRequest used on a route without a value of the catalogue.');
    }

    private function kind(): CatalogValueKind
    {
        return CatalogValueKind::of($this->catalogValue());
    }
}
