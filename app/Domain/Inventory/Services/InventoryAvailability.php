<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Data\ResourceAvailability;
use App\Domain\Inventory\Exceptions\InventoryActionNotAllowed;
use App\Domain\Settings\Services\Settings;
use App\Enums\AvailabilityMethod;
use App\Enums\InventoryResource;
use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\InventoryCommitment;
use App\Models\InventoryPool;
use App\Models\PartnershipTier;
use App\Models\Season;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Residuals of the inventory (M04 F-09, RB-01, RB-12; D-062, D-192, D-337): the only implementation,
 * used by the capacity pages, then by the availability page (F2-05), the configurator (F4) and the
 * approval (F5).
 *
 * - Residual = capacity − sum of the open commitments (released_at null) of the same season, club and
 *   resource; never stored.
 * - Seasonal method (D-062): a commitment counts for the whole season, whatever its contract period.
 *   With the method "per_match" of the settings the service refuses explicitly (D-293, D-337).
 * - The resources are the active values of the catalogue: active pools of an active visibility type,
 *   partnership tiers without the free package (no counter, D-052), hospitality variants.
 */
final class InventoryAvailability
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Every managed resource of a club, by kind, in the order of the catalogue.
     *
     * @return array{pool: list<ResourceAvailability>, partnership: list<ResourceAvailability>, hospitality: list<ResourceAvailability>}
     */
    public function forClub(Season $season, Club $club): array
    {
        $this->ensureSeasonal();

        $committed = $this->committedByResource($season, $club);
        $result = ['pool' => [], 'partnership' => [], 'hospitality' => []];

        foreach (InventoryResource::cases() as $resource) {
            $model = $resource->capacityModel();
            $capacities = $model::query()
                ->where('season_id', $season->id)
                ->where('club_id', $club->id)
                ->get()
                ->keyBy($resource->valueKey());

            foreach ($this->values($resource) as $value) {
                $capacity = $capacities->get($value->id);

                $result[$resource->value][] = new ResourceAvailability(
                    resource: $resource,
                    valueId: $value->id,
                    code: $value->code,
                    name: $value->name,
                    isConfigured: $capacity !== null,
                    isEnabled: (bool) $capacity?->getAttribute('is_enabled'),
                    capacity: (int) $capacity?->getAttribute($resource->quantityColumn()),
                    committed: $committed[$resource->value][$value->id] ?? 0,
                );
            }
        }

        return $result;
    }

    /**
     * Resources that have a capacity in the inventory (M04 §4), in the order of the catalogue.
     *
     * @return Collection<int, InventoryPool>|Collection<int, PartnershipTier>|Collection<int, HospitalityVariant>
     */
    public function values(InventoryResource $resource): Collection
    {
        return match ($resource) {
            InventoryResource::Pool => InventoryPool::query()
                ->where('is_active', true)
                ->whereHas('visibilityType', fn (Builder $type) => $type->where('is_active', true))
                ->orderBy('sort_order')->orderBy('name')->get(),
            InventoryResource::Partnership => PartnershipTier::query()
                ->where('is_active', true)
                ->where('is_free_package', false)
                ->orderBy('sort_order')->orderBy('name')->get(),
            InventoryResource::Hospitality => HospitalityVariant::query()
                ->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')->get(),
        };
    }

    /**
     * Committed quantity of one resource of a club in a season (open commitments only).
     */
    public function committed(Season $season, Club $club, InventoryResource $resource, int $valueId): int
    {
        $this->ensureSeasonal();

        return (int) InventoryCommitment::query()
            ->where('season_id', $season->id)
            ->where('club_id', $club->id)
            ->whereNull('released_at')
            ->where('resource_type', $resource->value)
            ->where($resource->valueKey(), $valueId)
            ->sum('quantity');
    }

    /**
     * D-293, D-337: the other methods are refused explicitly, never computed as seasonal.
     */
    public function ensureSeasonal(): void
    {
        $method = $this->settings->availabilityMethod();

        if ($method !== AvailabilityMethod::Seasonal) {
            throw InventoryActionNotAllowed::methodNotSupported($method->label());
        }
    }

    /**
     * @return array<string, array<int, int>> resource → value id → committed quantity
     */
    private function committedByResource(Season $season, Club $club): array
    {
        $committed = [];

        $rows = InventoryCommitment::query()
            ->where('season_id', $season->id)
            ->where('club_id', $club->id)
            ->whereNull('released_at')
            ->get(['resource_type', 'inventory_pool_id', 'partnership_tier_id', 'hospitality_variant_id', 'quantity']);

        foreach ($rows as $row) {
            /** @var int $valueId */
            $valueId = $row->getAttribute($row->resource_type->valueKey());
            $committed[$row->resource_type->value][$valueId] = ($committed[$row->resource_type->value][$valueId] ?? 0) + $row->quantity;
        }

        return $committed;
    }
}
