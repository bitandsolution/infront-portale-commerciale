<?php

namespace App\Domain\Inventory\Data;

use App\Enums\ClubSellabilityReason;
use App\Enums\PriceKind;
use App\Models\Club;

/**
 * What the configurator can sell of a club in a season (M06 RB-09, M03 RB-09, M04 F-09; D-085,
 * D-190; F2-05). Not selectable: the reason, and no options. Selectable: every option of the
 * catalogue with its status, in the order of the catalogue; the hidden ones (not managed, not
 * available) stay in the lists for the availability page and the server checks, and visible()
 * gives what the configurator shows.
 */
final readonly class ClubSellability
{
    /**
     * @param  list<SellableOption>  $led
     * @param  list<SellableOption>  $partnership
     * @param  list<SellableOption>  $hospitality
     */
    public function __construct(
        public Club $club,
        public ?ClubSellabilityReason $reason,
        public array $led = [],
        public array $partnership = [],
        public array $hospitality = [],
    ) {}

    public function isSelectable(): bool
    {
        return $this->reason === null;
    }

    /**
     * @return list<SellableOption>
     */
    public function options(): array
    {
        return [...$this->led, ...$this->partnership, ...$this->hospitality];
    }

    /**
     * Options shown in the configurator: available, or sold out and not selectable (FL-02 step 3).
     *
     * @param  list<SellableOption>  $options
     * @return list<SellableOption>
     */
    public static function visible(array $options): array
    {
        return array_values(array_filter($options, fn (SellableOption $option): bool => $option->status->isVisible()));
    }

    /**
     * The option of a kind by the code of its value (PREMIUM is both a tier and a variant).
     */
    public function option(PriceKind $kind, string $code): ?SellableOption
    {
        foreach ($this->options() as $option) {
            if ($option->kind === $kind && $option->code === $code) {
                return $option;
            }
        }

        return null;
    }
}
