<?php

namespace App\Domain\Proposals\Services;

use App\Domain\Inventory\Data\ResourceAvailability;
use App\Domain\Inventory\Services\InventoryAvailability;
use App\Domain\Proposals\Data\DraftClubData;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Data\InventoryNeed;
use App\Enums\CommitmentReleaseReason;
use App\Enums\InventoryResource;
use App\Models\Club;
use App\Models\InventoryCommitment;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\Season;
use App\Models\User;

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
 *
 * F5-04 (D-465): for a new version of an approved proposal the residual leaves out the commitments of
 * that proposal and every need carries what the previous version committed ($previous).
 * The approval and the new version also lock the capacities (D-216), write the commitments of a frozen
 * version (D-192, D-460) and release those of the previous version, here.
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
     * @param  int|null  $exceptProposalId  F5-04: the approved proposal being changed
     * @param  array<string, int>  $previous  F5-04: "club id|resource|value id" → quantity of the previous version
     * @return list<InventoryNeed>
     */
    public function needs(Season $season, array $variants, ?int $exceptProposalId = null, array $previous = []): array
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
            $resources[$clubId] ??= $this->resources($season, $item['club'], $exceptProposalId);
            $resource = $resources[$clubId][$item['resource']->value.'|'.$item['valueId']] ?? null;

            $needs[] = new InventoryNeed(
                club: $item['club'],
                resource: $item['resource'],
                valueId: $item['valueId'],
                name: $resource !== null ? $resource->name : '',
                requested: $item['quantity'],
                remaining: $resource !== null && $resource->isEnabled ? $resource->remaining() : 0,
                letters: $item['letters'],
                managed: $resource !== null && $resource->isEnabled,
                previous: $previous[$clubId.'|'.$item['resource']->value.'|'.$item['valueId']] ?? 0,
            );
        }

        return $needs;
    }

    /**
     * D-216: the capacity rows of these clubs in the season, locked table by table in order of id, so
     * that two approvals (or an approval and a new version) on the same clubs wait for each other.
     *
     * @param  list<int>  $clubIds
     */
    public function lockCapacities(Season $season, array $clubIds): void
    {
        if ($clubIds === []) {
            return;
        }

        foreach (InventoryResource::cases() as $resource) {
            $model = $resource->capacityModel();
            $model::query()
                ->where('season_id', $season->id)
                ->whereIn('club_id', $clubIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
        }
    }

    /**
     * One commitment per resource of every club row of a frozen version (D-192), with its contract
     * period; forced, with the reason, on the resources beyond the residual (F5-03, D-460).
     *
     * @param  array<string, InventoryNeed>  $forced  key of the need → need beyond the residual
     */
    public function commit(Season $season, Proposal $proposal, ProposalVersion $version, DraftEvaluation $evaluation, User $actor, array $forced, ?string $forcedReason): void
    {
        foreach ($version->clubs()->get() as $row) {
            $club = $evaluation->club($row->club_id);

            if ($club === null) {
                continue;
            }

            foreach ($this->demand($club->data) as $item) {
                $isForced = $forcedReason !== null && isset($forced[$row->club_id.'|'.$item['resource']->value.'|'.$item['valueId']]);

                InventoryCommitment::query()->create([
                    'season_id' => $season->id,
                    'club_id' => $row->club_id,
                    'proposal_id' => $proposal->id,
                    'proposal_version_id' => $version->id,
                    'proposal_version_club_id' => $row->id,
                    'resource_type' => $item['resource'],
                    'inventory_pool_id' => $item['resource'] === InventoryResource::Pool ? $item['valueId'] : null,
                    'partnership_tier_id' => $item['resource'] === InventoryResource::Partnership ? $item['valueId'] : null,
                    'hospitality_variant_id' => $item['resource'] === InventoryResource::Hospitality ? $item['valueId'] : null,
                    'quantity' => $item['quantity'],
                    'starts_on' => $version->starts_on,
                    'ends_on' => $version->ends_on,
                    'is_forced' => $isForced,
                    'forced_reason' => $isForced ? $forcedReason : null,
                    'created_by' => $actor->id,
                ]);
            }
        }
    }

    /**
     * F5-04 (D-465): the open commitments of a proposal by "club id|resource|value id".
     *
     * @return array<string, int>
     */
    public function committedBy(Proposal $proposal): array
    {
        $quantities = [];

        foreach (InventoryCommitment::query()->where('proposal_id', $proposal->id)->whereNull('released_at')->get() as $commitment) {
            /** @var int $valueId */
            $valueId = $commitment->getAttribute($commitment->resource_type->valueKey());
            $key = $commitment->club_id.'|'.$commitment->resource_type->value.'|'.$valueId;
            $quantities[$key] = ($quantities[$key] ?? 0) + $commitment->quantity;
        }

        return $quantities;
    }

    /**
     * Releases the open commitments of a proposal, once (D-192, D-346): the trigger allows only this
     * change. Returns how many were released.
     */
    public function release(Proposal $proposal, User $actor, CommitmentReleaseReason $reason): int
    {
        $now = now();

        return InventoryCommitment::query()
            ->where('proposal_id', $proposal->id)
            ->whereNull('released_at')
            ->update([
                'released_at' => $now,
                'released_by' => $actor->id,
                'release_reason' => $reason->value,
                'updated_at' => $now,
            ]);
    }

    /**
     * @return array<string, ResourceAvailability> "resource|value id" → availability
     */
    private function resources(Season $season, Club $club, ?int $exceptProposalId): array
    {
        $byKey = [];

        foreach ($this->availability->forClub($season, $club, $exceptProposalId) as $list) {
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
