<?php

namespace App\Http\Requests\Catalog;

use App\Enums\Ability;
use App\Support\Amounts;
use App\Support\Text;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One cell of the LED price list (M03 F-05, §8): the cost for 1′ per match in Italian notation
 * ("3.157,89"), ≥ 0 with at most 2 decimals (D-057); an empty cell means "not available" (D-190).
 */
class LedPriceRequest extends FormRequest
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
            'club_id' => ['bail', 'required', 'integer', 'exists:clubs,id'],
            'led_format_id' => ['bail', 'required', 'integer', 'exists:led_formats,id'],
            'unit_price' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value)) {
                    return;
                }

                $amount = Amounts::parseItalian($value);

                if ($amount === null) {
                    $fail(Text::get('catalog.validation.amount_format'));
                } elseif (Amounts::exceedsMax($amount)) {
                    $fail(Text::get('catalog.validation.amount_max'));
                }
            }],
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
     * Decimal string with two decimals, or null for "not available" (empty field).
     */
    public function unitPrice(): ?string
    {
        $text = $this->string('unit_price')->trim()->toString();

        return $text === '' ? null : Amounts::parseItalian($text);
    }
}
