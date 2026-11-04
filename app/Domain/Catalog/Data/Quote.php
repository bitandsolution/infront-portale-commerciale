<?php

namespace App\Domain\Catalog\Data;

use App\Domain\Sports\Data\MatchCount;
use App\Models\PriceList;

/**
 * List prices of a proposal (M06 F-05, M03 RB-01…RB-05; F2-06): one quote per club in the order
 * of the selection, the list total (sum of the clubs, D-103: never split nor discounted here), the
 * matches and campaign of the period from MatchCounter, and its warnings (e.g. matches prorated
 * without a calendar, D-187).
 */
final readonly class Quote
{
    /**
     * @param  list<ClubQuote>  $clubs
     * @param  list<string>  $warnings
     */
    public function __construct(
        public PriceList $priceList,
        public MatchCount $matchCount,
        public array $clubs,
        public string $listTotal,
        public array $warnings,
    ) {}
}
