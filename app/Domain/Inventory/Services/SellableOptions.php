<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Catalog\Services\PriceListGrid;
use App\Domain\Inventory\Data\ClubSellability;
use App\Domain\Inventory\Data\ResourceAvailability;
use App\Domain\Inventory\Data\SellableOption;
use App\Enums\ClubSellabilityReason;
use App\Enums\PriceKind;
use App\Enums\SellableStatus;
use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\PriceList;
use App\Models\Season;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the configurator can sell (M03 RB-09, M04 F-09, RB-08; M03-CA4, M04-CA3, M04-CA4; D-051,
 * D-052, D-065, D-066, D-071, D-085, D-190, D-337, D-347; F2-05): the only implementation, used by
 * the availability page, the command inventory:availability, then by the configurator (F4) and the
 * checks of the server on a proposal (F4, F5).
 *
 * - A club is selectable when it is an active Infront club enrolled in the season (D-337, D-341),
 *   the price list of the season is published (M03 RB-09) and it has at least one price in it
 *   (D-190), of any of the three kinds.
 * - LED format: price present (otherwise not available, M03-CA4); every consumed pool managed —
 *   active in the catalogue, capacity set with the flag on (otherwise not managed, M04-CA3, D-347);
 *   residual = the smallest residual of its pools (Jumbo and Domination consume the same seconds on
 *   both rows, D-051); zero or negative = sold out (M04-CA4).
 * - Partnership tier: the same with the counter of the tier; the free package "Libera" has neither
 *   price nor counter (D-052) and is always available in a selectable club.
 * - Hospitality variant: the same with the seats of the variant. The maximum of seats per club of a
 *   proposal (D-147) is a rule of the configurator (F4), not of the residual.
 * - Residuals come from InventoryAvailability (seasonal method only: "per match" is refused,
 *   D-293); the agent never sees a negative residual (M04 §10).
 */
final class SellableOptions
{
    public function __construct(
        private readonly PriceListGrid $grid,
        private readonly InventoryAvailability $availability,
    ) {}

    public function forClub(Season $season, Club $club): ClubSellability
    {
        $this->availability->ensureSeasonal();

        if (! $this->grid->hasClub($season, $club)) {
            return new ClubSellability($club, ClubSellabilityReason::NotInSeason);
        }

        $priceList = PriceList::query()->where('season_id', $season->id)->first();

        if ($priceList === null) {
            return new ClubSellability($club, ClubSellabilityReason::NoPriceList);
        }

        if (! $priceList->isPublished()) {
            return new ClubSellability($club, ClubSellabilityReason::PriceListDraft);
        }

        return $this->options($season, $club, $priceList);
    }

    /**
     * The selectable clubs of a season, in the order of the grids (by name): the list of the club
     * step of the configurator (M06 RB-09).
     *
     * @return list<ClubSellability>
     */
    public function selectableClubs(Season $season): array
    {
        $result = [];

        foreach ($this->grid->clubs($season) as $club) {
            $sellability = $this->forClub($season, $club);

            if ($sellability->isSelectable()) {
                $result[] = $sellability;
            }
        }

        return $result;
    }

    private function options(Season $season, Club $club, PriceList $priceList): ClubSellability
    {
        $formats = $this->grid->ledFormats()->load('pools');
        $tiers = PartnershipTier::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $variants = $this->grid->hospitalityVariants();

        $ledPrices = $this->prices($priceList, $club, PriceKind::Led, $formats->modelKeys());
        $tierPrices = $this->prices($priceList, $club, PriceKind::Partnership, $this->grid->partnershipTiers()->modelKeys());
        $seatPrices = $this->prices($priceList, $club, PriceKind::Hospitality, $variants->modelKeys());

        if ($ledPrices === [] && $tierPrices === [] && $seatPrices === []) {
            return new ClubSellability($club, ClubSellabilityReason::NoPrices);
        }

        $resources = $this->availability->forClub($season, $club);

        return new ClubSellability(
            club: $club,
            reason: null,
            led: $this->ledOptions($formats, $ledPrices, self::byValue($resources['pool'])),
            partnership: $this->tierOptions($tiers, $tierPrices, self::byValue($resources['partnership'])),
            hospitality: $this->variantOptions($variants, $seatPrices, self::byValue($resources['hospitality'])),
        );
    }

    /**
     * @param  Collection<int, LedFormat>  $formats
     * @param  array<int, string>  $prices
     * @param  array<int, ResourceAvailability>  $pools
     * @return list<SellableOption>
     */
    private function ledOptions(Collection $formats, array $prices, array $pools): array
    {
        $options = [];

        foreach ($formats as $format) {
            $price = $prices[$format->id] ?? null;
            $remaining = null;

            if ($price === null) {
                $status = SellableStatus::NotAvailable;
            } else {
                $consumed = [];

                foreach ($format->pools as $pool) {
                    $consumed[] = $pools[$pool->id] ?? null;
                }

                $remaining = self::smallestResidual($consumed);
                $status = self::status($remaining);
            }

            $options[] = new SellableOption(PriceKind::Led, $format->id, $format->code, $format->name, $status, $price, self::shown($status, $remaining));
        }

        return $options;
    }

    /**
     * @param  Collection<int, PartnershipTier>  $tiers
     * @param  array<int, string>  $prices
     * @param  array<int, ResourceAvailability>  $counters
     * @return list<SellableOption>
     */
    private function tierOptions(Collection $tiers, array $prices, array $counters): array
    {
        $options = [];

        foreach ($tiers as $tier) {
            if ($tier->is_free_package) {
                $options[] = new SellableOption(PriceKind::Partnership, $tier->id, $tier->code, $tier->name, SellableStatus::Available, null, null, isFreePackage: true);

                continue;
            }

            $options[] = $this->counted(PriceKind::Partnership, $tier, $prices[$tier->id] ?? null, $counters);
        }

        return $options;
    }

    /**
     * @param  Collection<int, HospitalityVariant>  $variants
     * @param  array<int, string>  $prices
     * @param  array<int, ResourceAvailability>  $counters
     * @return list<SellableOption>
     */
    private function variantOptions(Collection $variants, array $prices, array $counters): array
    {
        $options = [];

        foreach ($variants as $variant) {
            $options[] = $this->counted(PriceKind::Hospitality, $variant, $prices[$variant->id] ?? null, $counters);
        }

        return $options;
    }

    /**
     * An option with a single counter (partnership tier or hospitality variant).
     *
     * @param  array<int, ResourceAvailability>  $counters
     */
    private function counted(PriceKind $kind, PartnershipTier|HospitalityVariant $value, ?string $price, array $counters): SellableOption
    {
        if ($price === null) {
            return new SellableOption($kind, $value->id, $value->code, $value->name, SellableStatus::NotAvailable, null, null);
        }

        $remaining = self::smallestResidual([$counters[$value->id] ?? null]);
        $status = self::status($remaining);

        return new SellableOption($kind, $value->id, $value->code, $value->name, $status, $price, self::shown($status, $remaining));
    }

    /**
     * Prices of the club in the list for the active columns of a kind, by value id.
     *
     * @param  array<int, mixed>  $columnIds
     * @return array<int, string>
     */
    private function prices(PriceList $priceList, Club $club, PriceKind $kind, array $columnIds): array
    {
        $model = $kind->priceModel();
        $prices = [];

        $rows = $model::query()
            ->where('price_list_id', $priceList->id)
            ->where('club_id', $club->id)
            ->whereIn($kind->columnKey(), $columnIds)
            ->get();

        foreach ($rows as $row) {
            /** @var int $valueId */
            $valueId = $row->getAttribute($kind->columnKey());
            /** @var string $amount */
            $amount = $row->getAttribute($kind->amountColumn());
            $prices[$valueId] = $amount;
        }

        return $prices;
    }

    /**
     * Smallest residual of the resources, or null when one of them is not managed: missing from the
     * availability (value inactive in the catalogue), capacity never set (D-347) or flag off
     * (D-066); also null without any resource (a format that consumes no pool sells nothing).
     *
     * @param  list<ResourceAvailability|null>  $resources
     */
    private static function smallestResidual(array $resources): ?int
    {
        if ($resources === []) {
            return null;
        }

        $smallest = null;

        foreach ($resources as $resource) {
            if ($resource === null || ! $resource->isConfigured || ! $resource->isEnabled) {
                return null;
            }

            $smallest = $smallest === null ? $resource->remaining() : min($smallest, $resource->remaining());
        }

        return $smallest;
    }

    private static function status(?int $remaining): SellableStatus
    {
        return match (true) {
            $remaining === null => SellableStatus::NotManaged,
            $remaining <= 0 => SellableStatus::SoldOut,
            default => SellableStatus::Available,
        };
    }

    /** What the agent sees (M04 §10): never negative, nothing when not managed. */
    private static function shown(SellableStatus $status, ?int $remaining): ?int
    {
        return $status === SellableStatus::NotManaged || $remaining === null ? null : max(0, $remaining);
    }

    /**
     * @param  list<ResourceAvailability>  $items
     * @return array<int, ResourceAvailability>
     */
    private static function byValue(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            $result[$item->valueId] = $item;
        }

        return $result;
    }
}
