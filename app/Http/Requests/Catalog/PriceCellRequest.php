<?php

namespace App\Http\Requests\Catalog;

use App\Enums\Ability;
use App\Support\Amounts;
use App\Support\Text;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One cell of a price list grid (M03 F-05, F-07, F-09, §8): club, value of the catalogue (format,
 * tier or variant, after the kind of the route) and amount in Italian notation ("3.157,89"), ≥ 0
 * with at most 2 decimals (D-057); an empty cell means "not available" (D-190).
 */
class PriceCellRequest extends FormRequest
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
            'column_id' => ['bail', 'required', 'integer', 'min:1'],
            'amount' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
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
    public function amount(): ?string
    {
        $text = $this->string('amount')->trim()->toString();

        return $text === '' ? null : Amounts::parseItalian($text);
    }
}
