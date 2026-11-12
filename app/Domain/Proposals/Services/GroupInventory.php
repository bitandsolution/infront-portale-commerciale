<?php

namespace App\Domain\Proposals\Services;

use App\Domain\Inventory\Data\ResourceAvailability;
use App\Domain\Inventory\Services\InventoryAvailability;
use App\Domain\Proposals\Data\DraftClubData;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Data\InventoryNeed;
use App\Enums\InventoryResource;
use App\Models\Club;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Season;

/**
 * Inventory asked by a group of variants (M04 §7; D-051, D-052, D-192, D-216, D-396; DM-03b): the
 * only place that turns the configuration of a club into quantities of resources, used by the check
 * of the approval, by its commitments and by the detail of a group.
 *
 * - LED: the seconds per match on every pool of the format (Jumbo and Domination consume the same
 *   seconds on the 1st and on the 2nd row, D-051);
 * - partnership: one contract of the tier; the free package "Libera" consumes nothing (D-052);
 * - hospitality: the seats of every variant.
 *
 * All the variants commit at the approval (D-396): their quantities are added up per club and
 * resource and compared with the residual (capacity − open commitments, InventoryAvailability).
 */
final class GroupInventory
{
    /** @var array<int, list<int>> format id → pool ids */
    private array $formatPools = [];

    /** @var array<int, bool> tier id → free package */
    private array $freeTiers = [];

    public function __construct(private readonly InventoryAvailability $availability) {}

    /**
     * The quantities one club of a variant asks for.
     *
     * @return list<array{resource: InventoryResource, valueId: int, quantity: int}>
     */
    public function demand(DraftClubData $club): array
    {
        $demand = [];

        if ($club->ledFormatId !== null && $club->ledSeconds > 0) {
            foreach ($this->pools($club->ledFormatId) as $poolId) {
                $demand[] = ['resource' => InventoryResource::Pool, 'valueId' => $poolId, 'quantity' => $club->ledSeconds];
            }
        }

        if ($club->tierId !== null && ! $this->isFree($club->tierId)) {
            $demand[] = ['resource' => InventoryResource::Partnership, 'valueId' => $club->tierId, 'quantity' => 1];
        }

        foreach ($club->seats as $variantId => $seats) {
            if ($seats > 0) {
                $demand[] = ['resource' => InventoryResource::Hospitality, 'valueId' => $variantId, 'quantity' => $seats];
            }
        }

        return $demand;
    }

    /**
     * The needs of the variants of a group, club by club and resource by resource, in the order of
     * the clubs and of the catalogue.
     *
     * @param  array<string, DraftEvaluation>  $variants  letter → evaluation ('' for a single proposal)
     * @return list<InventoryNeed>
     */
    public function needs(Season $season, array $variants): array
    {
        /** @var array<string, array{club: Club, resource: InventoryResource, valueId: int, quantity: int, letters: list<string>}> $requested */
        $requested = [];

        foreach ($variants as $letter => $evaluation) {
            foreach ($evaluation->clubs as $club) {
                foreach ($this->demand($club->data) as $item) {
                    $key = $club->club->id.'|'.$item['resource']->value.'|'.$item['valueId'];
                    $requested[$key] ??= ['club' => $club->club, 'resource' => $item['resource'], 'valueId' => $item['valueId'], 'quantity' => 0, 'letters' => []];
                    $requested[$key]['quantity'] += $item['quantity'];

                    if ($letter !== '' && ! in_array($letter, $requested[$key]['letters'], true)) {
                        $requested[$key]['letters'][] = $letter;
                    }
                }
            }
        }

        $needs = [];
        /** @var array<int, array<string, ResourceAvailability>> $resources */
        $resources = [];

        foreach ($requested as $item) {
            $clubId = $item['club']->id;
            $resources[$clubId] ??= $this->resources($season, $item['club']);
            $resource = $resources[$clubId][$item['resource']->value.'|'.$item['valueId']] ?? null;

            $needs[] = new InventoryNeed(
                club: $item['club'],
                resource: $item['resource'],
                valueId: $item['valueId'],
                name: $resource !== null ? $resource->name : '',
                requested: $item['quantity'],
                remaining: $resource !== null && $resource->isEnabled ? $resource->remaining() : 0,
                letters: $item['letters'],
            );
        }

        return $needs;
    }

    /**
     * @return array<string, ResourceAvailability> "resource|value id" → availability
     */
    private function resources(Season $season, Club $club): array
    {
        $byKey = [];

        foreach ($this->availability->forClub($season, $club) as $list) {
            foreach ($list as $resource) {
                $byKey[$resource->resource->value.'|'.$resource->valueId] = $resource;
            }
        }

        return $byKey;
    }

    /**
     * @return list<int>
     */
    private function pools(int $formatId): array
    {
        if (! array_key_exists($formatId, $this->formatPools)) {
            $format = LedFormat::query()->with('pools')->find($formatId);
            $this->formatPools[$formatId] = $format !== null ? array_values(array_map(fn (InventoryPool $pool): int => $pool->id, $format->pools->all())) : [];
        }

        return $this->formatPools[$formatId];
    }

    private function isFree(int $tierId): bool
    {
        return $this->freeTiers[$tierId] ??= (bool) PartnershipTier::query()->whereKey($tierId)->value('is_free_package');
    }
}
