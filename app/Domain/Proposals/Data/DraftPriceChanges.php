<?php

namespace App\Domain\Proposals\Data;

/**
 * Differences between the prices saved on a draft and those calculated when it is reopened (M06
 * RB-12, D-045, M03-CA6; ux-architettura §4.5 "Listino cambiato"; F4-04): the clubs whose price
 * changed and the list total, saved and current.
 */
final readonly class DraftPriceChanges
{
    /**
     * @param  list<ClubPriceChange>  $clubs
     */
    public function __construct(
        public array $clubs,
        public string $savedListTotal,
        public string $currentListTotal,
    ) {}
}
