<?php

namespace App\Enums;

/**
 * Status of the price list of a season (M03 F-11; D-333), stored in price_lists.status (C8).
 *
 * - draft: born empty, prepared by the admin, not usable by the configurator (RB-09);
 * - published: usable by the configurator; publishing is final, there is no way back to draft
 *   (D-333), while the prices stay editable with an audit event for every price.
 */
enum PriceListStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    /**
     * Italian label (lang/it/catalog.php).
     */
    public function label(): string
    {
        $label = __('catalog.price_list_statuses.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
