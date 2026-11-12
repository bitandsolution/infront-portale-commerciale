<?php

namespace App\Domain\Proposals\Data;

use App\Enums\DiscountType;

/**
 * The discount on the total of a variant (M07 F-04, RB-03, RB-04; D-097, D-103, D-401, D-408): how it
 * was entered (percentage or amount, null = no discount), the discount in euro, the final total and
 * the equivalent percentage (M07-CA3). Amounts are decimal strings with two decimals; the percentage
 * has two decimals too. The club rows stay at list price.
 */
final readonly class AppliedDiscount
{
    public function __construct(
        public string $listTotal,
        public ?DiscountType $type,
        public ?string $value,
        public string $amount,
        public string $finalTotal,
        public string $percent,
    ) {}

    public function isNone(): bool
    {
        return $this->type === null;
    }
}
