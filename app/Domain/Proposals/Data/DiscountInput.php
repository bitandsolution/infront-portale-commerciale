<?php

namespace App\Domain\Proposals\Data;

use App\Enums\DiscountType;

/**
 * The discount entered by admin or manager for one variant at the approval (D-401, D-408): type and
 * value as typed (a decimal string), both null for no discount.
 */
final readonly class DiscountInput
{
    public function __construct(
        public ?DiscountType $type = null,
        public ?string $value = null,
    ) {}
}
