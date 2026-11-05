<?php

namespace App\Domain\Clients\Rules;

use App\Domain\Clients\Services\VatNumber;
use App\Support\Text;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * VAT number field of the client form (M05 §8; D-361): 11 digits with the check digit, after
 * dropping spaces and an "IT" prefix. An empty field is handled by "nullable".
 */
final class ItalianVatNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = VatNumber::clean(is_string($value) ? $value : null);

        if ($digits === null) {
            return;
        }

        if (preg_match('/^[0-9]{11}$/', $digits) !== 1) {
            $fail(Text::get('clients.validation.vat_number_format'));

            return;
        }

        if (! VatNumber::isValid($digits)) {
            $fail(Text::get('clients.validation.vat_number_check'));
        }
    }
}
