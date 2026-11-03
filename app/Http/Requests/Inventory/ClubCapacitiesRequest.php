<?php

namespace App\Http\Requests\Inventory;

use App\Domain\Inventory\Data\CapacityInput;
use App\Domain\Settings\Services\Settings;
use App\Enums\Ability;
use App\Enums\InventoryResource;
use App\Support\LedDuration;
use App\Support\Text;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Capacities of a club in a season (M04 F-01…F-03, §9): for every resource the flag "managed" and
 * the quantity: LED pools as minutes per match in multiples of the step of the settings (D-063),
 * partnership contracts and hospitality seats as whole numbers ≥ 0. "confirmed" is the
 * confirmation of capacities under the committed quantity (RB-10).
 */
class ClubCapacitiesRequest extends FormRequest
{
    /** Longest LED capacity per match: the length of a match. */
    public const MAX_SECONDS = 5400;

    public const MAX_CONTRACTS = 999;

    public const MAX_SEATS = 9999;

    public function authorize(): bool
    {
        return $this->user()?->can(Ability::InventoryCapacityManage->value) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $step = app(Settings::class)->ledStepSeconds();

        return [
            'season_id' => ['bail', 'required', 'integer', 'exists:seasons,id'],
            'confirmed' => ['boolean'],
            'pools' => ['array'],
            'pools.*.enabled' => ['required', 'boolean'],
            'pools.*.minutes' => ['required', 'string', 'max:10', function (string $attribute, mixed $value, Closure $fail) use ($step): void {
                $seconds = is_string($value) ? LedDuration::parseMinutes($value) : null;

                if ($seconds === null) {
                    $fail(Text::get('inventory.validation.minutes_format'));
                } elseif ($seconds % $step !== 0) {
                    $fail(Text::get('inventory.validation.minutes_step', ['step' => LedDuration::format($step)]));
                } elseif ($seconds > self::MAX_SECONDS) {
                    $fail(Text::get('inventory.validation.minutes_max', ['max' => LedDuration::format(self::MAX_SECONDS)]));
                }
            }],
            'tiers' => ['array'],
            'tiers.*.enabled' => ['required', 'boolean'],
            'tiers.*.quantity' => ['required', 'integer', 'between:0,'.self::MAX_CONTRACTS],
            'variants' => ['array'],
            'variants.*.enabled' => ['required', 'boolean'],
            'variants.*.seats' => ['required', 'integer', 'between:0,'.self::MAX_SEATS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = __('inventory.attributes');
        $names = [];

        foreach (is_array($attributes) ? $attributes : [] as $field => $name) {
            if (is_string($field) && is_string($name)) {
                $names[$field] = $name;
            }
        }

        return $names;
    }

    /**
     * @return list<CapacityInput>
     */
    public function capacityInputs(): array
    {
        $inputs = [];

        foreach ([
            [InventoryResource::Pool, 'pools', 'minutes'],
            [InventoryResource::Partnership, 'tiers', 'quantity'],
            [InventoryResource::Hospitality, 'variants', 'seats'],
        ] as [$resource, $group, $field]) {
            foreach ((array) $this->input($group, []) as $valueId => $row) {
                if (! is_numeric($valueId) || ! is_array($row)) {
                    continue;
                }

                $quantity = $resource === InventoryResource::Pool
                    ? (LedDuration::parseMinutes((string) ($row[$field] ?? '')) ?? 0)
                    : (int) ($row[$field] ?? 0);

                $inputs[] = new CapacityInput($resource, (int) $valueId, filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN), $quantity);
            }
        }

        return $inputs;
    }
}
