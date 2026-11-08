<?php

namespace App\Enums;

/**
 * How the discount on the total of a version is expressed (M07 F-04; D-097, D-103), stored in
 * proposal_versions.discount_type (C8): a percentage of the list total or an amount in euro. Only on
 * the total, never on the club rows. Set by the manager or the admin in F5.
 */
enum DiscountType: string
{
    case Percent = 'percent';
    case Amount = 'amount';
}
