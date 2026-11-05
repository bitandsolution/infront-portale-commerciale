<?php

namespace App\Http\Requests\Clients;

use App\Domain\Clients\Data\CommoditySectorData;
use App\Models\CommoditySector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * New commodity sector or change of a sector (M05 F-05; D-357): name (unique, the collation ignores
 * case and accents), order, active.
 */
class CommoditySectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sector = $this->route('commoditySector');

        return $sector instanceof CommoditySector
            ? $this->user()?->can('update', $sector) ?? false
            : $this->user()?->can('create', CommoditySector::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $sector = $this->route('commoditySector');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('commodity_sectors', 'name')->ignore($sector instanceof CommoditySector ? $sector->id : null)],
            'sort_order' => ['bail', 'required', 'integer', 'between:0,9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = __('clients.attributes');
        $names = [];

        foreach (is_array($attributes) ? $attributes : [] as $field => $name) {
            if (is_string($field) && is_string($name)) {
                $names[$field] = $name;
            }
        }

        return $names;
    }

    public function sectorData(): CommoditySectorData
    {
        return new CommoditySectorData(
            name: $this->string('name')->squish()->toString(),
            sortOrder: $this->integer('sort_order'),
            isActive: $this->boolean('is_active'),
        );
    }
}
