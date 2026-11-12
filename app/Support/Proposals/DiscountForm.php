<?php

namespace App\Support\Proposals;

use App\Domain\Proposals\Data\DiscountInput;
use App\Enums\DiscountType;
use App\Support\Amounts;
use App\Support\Text;

/**
 * The discounts typed in the detail of a group (D-401, D-408; DM-03b), as the page sends them, for
 * the approval and for its preview: proposal id → type ("percent", "amount" or empty) and value in
 * Italian notation ("15", "12,5", "10.000"). An empty value is no discount.
 */
final class DiscountForm
{
    /**
     * @param  array<mixed>  $input  proposal id → ['type' => …, 'value' => …]
     * @return array{discounts: array<int, DiscountInput>, errors: array<int, string>}
     */
    public static function parse(array $input): array
    {
        $discounts = [];
        $errors = [];

        foreach ($input as $id => $row) {
            if (! is_numeric($id) || ! is_array($row)) {
                continue;
            }

            $type = is_string($row['type'] ?? null) ? DiscountType::tryFrom($row['type']) : null;
            $text = is_string($row['value'] ?? null) ? trim($row['value']) : '';

            if ($type === null || $text === '') {
                $discounts[(int) $id] = new DiscountInput;

                continue;
            }

            $value = Amounts::parseItalian($text);

            if ($value === null) {
                $errors[(int) $id] = Text::get('ui.proposals.approval.discount_invalid');
                $discounts[(int) $id] = new DiscountInput;

                continue;
            }

            $discounts[(int) $id] = new DiscountInput($type, $value);
        }

        return ['discounts' => $discounts, 'errors' => $errors];
    }
}
