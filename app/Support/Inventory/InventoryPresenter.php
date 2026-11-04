<?php

namespace App\Support\Inventory;

use App\Domain\Catalog\Services\PriceListGrid;
use App\Domain\Inventory\Data\ResourceAvailability;
use App\Domain\Inventory\Services\InventoryAvailability;
use App\Enums\InventoryResource;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\Competition;
use App\Models\Season;
use App\Support\LedDuration;

/**
 * Data of the pages "Magazzino › Capacità" (M04 F-01…F-03; D-191, D-337) for the frontend: the clubs
 * of the season with their capacities by resource, and the form of the capacities of a club with
 * committed and residual. Quantities come formatted from the server (minutes as m′ss″): the pages
 * never compute residuals (CLAUDE.md §4).
 */
final class InventoryPresenter
{
    public function __construct(
        private readonly PriceListGrid $grid,
        private readonly InventoryAvailability $availability,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function capacitiesPage(Season $season): array
    {
        $clubs = $this->grid->clubs($season);

        return [
            'season' => ['id' => $season->id, 'code' => $season->code, 'readOnly' => $season->isReadOnly()],
            'groups' => array_map(fn (InventoryResource $resource): array => [
                'resource' => $resource->value,
                'columns' => $this->availability->values($resource)
                    ->map(fn ($value): array => ['id' => $value->id, 'name' => $value->name])
                    ->values()->all(),
            ], InventoryResource::cases()),
            'competitions' => self::competitions($clubs),
            'rows' => $clubs->map(function (Club $club) use ($season): array {
                $resources = $this->availability->forClub($season, $club);
                $cells = [];

                foreach ($resources as $resource => $items) {
                    foreach ($items as $item) {
                        $cells[$resource][(string) $item->valueId] = self::cell($item);
                    }
                }

                return [
                    'clubId' => $club->id,
                    'name' => $club->name,
                    'competitionIds' => $club->enrollments
                        ->map(fn (ClubEnrollment $enrollment): int => $enrollment->competitionSeason->competition_id)
                        ->unique()->values()->all(),
                    'cells' => $cells,
                    'editUrl' => route('inventory.capacities.edit', [$club, 'stagione' => $season->id]),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clubPage(Season $season, Club $club, int $step): array
    {
        $resources = $this->availability->forClub($season, $club);

        return [
            'season' => ['id' => $season->id, 'code' => $season->code, 'readOnly' => $season->isReadOnly()],
            'club' => ['id' => $club->id, 'name' => $club->name],
            'step' => ['seconds' => $step, 'label' => LedDuration::format($step), 'minutes' => LedDuration::minutesForInput($step)],
            'groups' => array_map(fn (InventoryResource $resource): array => [
                'resource' => $resource->value,
                'rows' => array_map(fn (ResourceAvailability $item): array => [
                    'valueId' => $item->valueId,
                    'name' => $item->name,
                    'isEnabled' => $item->isEnabled,
                    'quantity' => $resource === InventoryResource::Pool ? LedDuration::minutesForInput($item->capacity) : (string) $item->capacity,
                    'capacity' => self::quantity($resource, $item->capacity),
                    'committed' => self::quantity($resource, $item->committed),
                    'remaining' => self::quantity($resource, $item->remaining()),
                    'isBelowCommitted' => $item->isBelowCommitted(),
                ], $resources[$resource->value]),
            ], InventoryResource::cases()),
            'saveUrl' => route('inventory.capacities.update', $club),
            'backUrl' => route('pages.inventory_capacity'),
        ];
    }

    public static function quantity(InventoryResource $resource, int $quantity): string
    {
        return $resource === InventoryResource::Pool ? LedDuration::format($quantity) : (string) $quantity;
    }

    /**
     * @return array{state: string, display: string|null, isBelowCommitted: bool}
     */
    private static function cell(ResourceAvailability $item): array
    {
        return [
            'state' => ! $item->isConfigured ? 'not_configured' : ($item->isEnabled ? 'managed' : 'not_managed'),
            'display' => $item->isConfigured && $item->isEnabled ? self::quantity($item->resource, $item->capacity) : null,
            'isBelowCommitted' => $item->isBelowCommitted(),
        ];
    }

    /**
     * Competitions of the clubs of a grid, for the filter (also used by AvailabilityPresenter).
     *
     * @param  iterable<Club>  $clubs
     * @return list<array{id: int, name: string}>
     */
    public static function competitions(iterable $clubs): array
    {
        $competitions = [];

        foreach ($clubs as $club) {
            foreach ($club->enrollments as $enrollment) {
                $competition = $enrollment->competitionSeason->competition;
                $competitions[$competition->id] = $competition;
            }
        }

        usort($competitions, fn (Competition $a, Competition $b): int => [$a->sort_order, $a->name] <=> [$b->sort_order, $b->name]);

        return array_map(fn (Competition $competition): array => ['id' => $competition->id, 'name' => $competition->name], $competitions);
    }
}
