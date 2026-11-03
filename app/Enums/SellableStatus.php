<?php

namespace App\Enums;

/**
 * Status of an option of a club for the configurator (M03 RB-09, M04 RB-08; D-066, D-085, D-347;
 * F2-05), computed by SellableOptions and never stored:
 *
 * - available: price in the published list, resource managed, residual above zero;
 * - sold_out: price present and resource managed, residual zero or negative: visible but not
 *   selectable (M04-CA4, FL-02 step 3);
 * - not_managed: price present but a consumed pool, or the counter of the tier or variant, is not
 *   managed (flag off, capacity never set, value of the catalogue inactive): hidden (M04-CA3);
 * - not_available: no price in the list for the club (empty cell): hidden (M03-CA4).
 */
enum SellableStatus: string
{
    case Available = 'available';
    case SoldOut = 'sold_out';
    case NotManaged = 'not_managed';
    case NotAvailable = 'not_available';

    /** Shown in the configurator: available, or sold out but not selectable (FL-02 step 3). */
    public function isVisible(): bool
    {
        return $this === self::Available || $this === self::SoldOut;
    }

    public function isSelectable(): bool
    {
        return $this === self::Available;
    }

    /**
     * Italian label (lang/it/inventory.php).
     */
    public function label(): string
    {
        $label = __('inventory.sellable_statuses.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
