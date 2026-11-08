<?php

namespace App\Domain\Proposals\Data;

use App\Domain\Catalog\Data\ClubQuote;
use App\Domain\Inventory\Data\ClubSellability;
use App\Models\Club;

/**
 * One club of a draft evaluated by the server (M06 §5, §8, §9; F4-03): what can be sold of it
 * (SellableOptions), its list prices and home matches of the period (PriceCalculator, MatchCounter)
 * and every error found, by field, without stopping at the first one.
 *
 * Fields of the errors: "club" (not selectable, not enrolled in a league, no season), "tier",
 * "led_format", "led_seconds", "matches" (LED without home matches), "seats" (total), "seats.<variant
 * id>" and "elements" (no element, D-373). The quote is null when the club cannot be priced (not
 * selectable, not enrolled in a league, no period).
 */
final readonly class ClubEvaluation
{
    /**
     * @param  array<string, string>  $errors
     */
    public function __construct(
        public DraftClubData $data,
        public Club $club,
        public ?ClubSellability $sellability,
        public ?ClubQuote $quote,
        public array $errors,
    ) {}

    public function total(): string
    {
        return $this->quote !== null ? $this->quote->total : '0.00';
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * RB-08, D-373: no element configured; blocks the summary and, with F5, the submission.
     */
    public function lacksElements(): bool
    {
        return isset($this->errors['elements']);
    }
}
