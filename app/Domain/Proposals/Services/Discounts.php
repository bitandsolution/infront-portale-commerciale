<?php

namespace App\Domain\Proposals\Services;

use App\Domain\Proposals\Data\AppliedDiscount;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Enums\DiscountType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Discount on the total of a variant (M07 F-04, §8, RB-03, RB-04, CA2, CA3; D-097, D-103, D-104, D-401,
 * D-408): the only calculation, used by the approval and by the preview of the detail of a group.
 *
 * - Percentage between 0 and 100: discount = list total × % / 100, rounded to the cent (0.5 up,
 *   D-336); 155,680.00 − 15% = 132,328.00.
 * - Amount between 0 and the list total: the equivalent percentage is shown (M07-CA3).
 * - The final total is never negative (D-103); no limit for the manager (D-104).
 * - A zero value is no discount.
 */
final class Discounts
{
    /**
     * @throws ProposalsActionNotAllowed when the value is out of range
     */
    public function apply(string $listTotal, ?DiscountType $type, ?string $value): AppliedDiscount
    {
        $list = BigDecimal::of($listTotal)->toScale(2, RoundingMode::HalfUp);

        if ($type === null || $value === null || BigDecimal::of($value)->isZero()) {
            return new AppliedDiscount((string) $list, null, null, '0.00', (string) $list, '0.00');
        }

        $entered = BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp);

        if ($entered->isNegative()) {
            throw ProposalsActionNotAllowed::discountOutOfRange();
        }

        if ($type === DiscountType::Percent) {
            if ($entered->isGreaterThan(100)) {
                throw ProposalsActionNotAllowed::discountOutOfRange();
            }

            $amount = $list->multipliedBy($entered)->dividedBy(100, 2, RoundingMode::HalfUp);
            $percent = $entered;
        } else {
            if ($entered->isGreaterThan($list)) {
                throw ProposalsActionNotAllowed::discountOutOfRange();
            }

            $amount = $entered;
            $percent = $list->isZero() ? BigDecimal::zero()->toScale(2) : $entered->multipliedBy(100)->dividedBy($list, 2, RoundingMode::HalfUp);
        }

        $final = $list->minus($amount);

        return new AppliedDiscount(
            (string) $list,
            $type,
            (string) $entered,
            (string) $amount,
            (string) ($final->isNegative() ? BigDecimal::zero()->toScale(2) : $final),
            (string) $percent,
        );
    }
}
