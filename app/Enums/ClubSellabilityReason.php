<?php

namespace App\Enums;

/**
 * Why a club is not selectable in the configurator of a season (M06 RB-09, M03 RB-09; D-085,
 * D-190, D-337; F2-05):
 *
 * - not_in_season: not an active Infront club enrolled in a competition of the season (D-337, D-341);
 * - no_price_list: the season has no price list yet;
 * - price_list_draft: the price list of the season is still a draft (M03 RB-09);
 * - no_prices: the published price list has no price for the club (D-190: a club is selectable when
 *   it has at least one price in the published list).
 */
enum ClubSellabilityReason: string
{
    case NotInSeason = 'not_in_season';
    case NoPriceList = 'no_price_list';
    case PriceListDraft = 'price_list_draft';
    case NoPrices = 'no_prices';

    /**
     * Italian label (lang/it/inventory.php).
     */
    public function label(): string
    {
        $label = __('inventory.sellability_reasons.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
