<?php

namespace App\Support\Inventory;

use App\Domain\Catalog\Services\PriceListGrid;
use App\Domain\Inventory\Data\ClubSellability;
use App\Domain\Inventory\Data\ResourceAvailability;
use App\Domain\Inventory\Data\SellableOption;
use App\Domain\Inventory\Services\InventoryAvailability;
use App\Domain\Inventory\Services\SellableOptions;
use App\Enums\SellableStatus;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\InventoryPool;
use App\Models\Season;
use App\Support\LedDuration;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;

/**
 * Data of the page "Magazzino › Disponibilità" (M04 F-06; mockup "Magazzino"; D-337; F2-05): for
 * each club of the season the availability of every LED pool (capacity, committed, pending,
 * residual), the counters of partnership and hospitality, and what the configurator can sell
 * (SellableOptions). Quantities come formatted from the server, residuals and the share of the bar
 * included: the page never computes them (CLAUDE.md §4). "In attesa" stays empty until F5 (D-337
 * point 2).
 */
final class AvailabilityPresenter
{
    public function __construct(
        private readonly PriceListGrid $grid,
        private readonly InventoryAvailability $availability,
        private readonly SellableOptions $sellable,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function page(Season $season, bool $canEditCapacities): array
    {
        $clubs = $this->grid->clubs($season);
        $formatsByPool = $this->formatsByPool();

        return [
            'season' => ['id' => $season->id, 'code' => $season->code, 'readOnly' => $season->isReadOnly()],
            'pools' => InventoryPool::query()
                ->whereHas('visibilityType', fn (Builder $type) => $type->where('is_active', true))
                ->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (InventoryPool $pool): array => ['id' => $pool->id, 'name' => $pool->name, 'isActive' => $pool->is_active])
                ->values()->all(),
            'competitions' => InventoryPresenter::competitions($clubs),
            'rows' => $clubs->map(fn (Club $club): array => $this->row($season, $club, $formatsByPool, $canEditCapacities))->values()->all(),
        ];
    }

    /**
     * @param  array<int, list<string>>  $formatsByPool
     * @return array<string, mixed>
     */
    private function row(Season $season, Club $club, array $formatsByPool, bool $canEditCapacities): array
    {
        $resources = $this->availability->forClub($season, $club);
        $sellability = $this->sellable->forClub($season, $club);
        $pools = [];

        foreach ($resources['pool'] as $item) {
            $formats = $formatsByPool[$item->valueId] ?? [];

            $pools[(string) $item->valueId] = [
                ...self::cell($item),
                'notSellable' => $formats === []
                    ? Text::get('ui.inventory.availability.no_formats')
                    : Text::get('ui.inventory.availability.not_sellable', ['formats' => implode(', ', $formats)]),
            ];
        }

        return [
            'clubId' => $club->id,
            'name' => $club->name,
            'competitionIds' => $club->enrollments
                ->map(fn (ClubEnrollment $enrollment): int => $enrollment->competitionSeason->competition_id)
                ->unique()->values()->all(),
            'pools' => $pools,
            'tiers' => array_map(fn (ResourceAvailability $item): array => ['name' => $item->name, ...self::cell($item)], $resources['partnership']),
            'variants' => array_map(fn (ResourceAvailability $item): array => ['name' => $item->name, ...self::cell($item)], $resources['hospitality']),
            'sellability' => self::sellability($sellability),
            'editUrl' => $canEditCapacities ? route('inventory.capacities.edit', [$club, 'stagione' => $season->id]) : null,
        ];
    }

    /**
     * @return array{state: string, capacity: string|null, committed: string, pending: null, remaining: string|null, share: int, isSoldOut: bool, isBelowCommitted: bool}
     */
    private static function cell(ResourceAvailability $item): array
    {
        $managed = $item->isConfigured && $item->isEnabled;

        return [
            'state' => ! $item->isConfigured ? 'not_configured' : ($item->isEnabled ? 'managed' : 'not_managed'),
            'capacity' => $managed ? InventoryPresenter::quantity($item->resource, $item->capacity) : null,
            'committed' => InventoryPresenter::quantity($item->resource, $item->committed),
            // Proposals waiting for approval arrive with F5 (D-337 point 2).
            'pending' => null,
            'remaining' => $managed ? InventoryPresenter::quantity($item->resource, $item->remaining()) : null,
            'share' => $managed && $item->capacity > 0 ? max(0, min(100, intdiv($item->remaining() * 100, $item->capacity))) : 0,
            'isSoldOut' => $managed && $item->remaining() <= 0,
            'isBelowCommitted' => $item->isBelowCommitted(),
        ];
    }

    /**
     * @return array{selectable: bool, reason: string|null, formats: list<array{name: string, status: string, label: string}>}
     */
    private static function sellability(ClubSellability $sellability): array
    {
        return [
            'selectable' => $sellability->isSelectable(),
            'reason' => $sellability->reason?->label(),
            'formats' => array_map(fn (SellableOption $option): array => [
                'name' => $option->name,
                'status' => $option->status->value,
                'label' => $option->status === SellableStatus::Available && $option->remaining !== null
                    ? $option->status->label().' · '.LedDuration::format($option->remaining)
                    : $option->status->label(),
            ], $sellability->led),
        ];
    }

    /**
     * Active LED formats by consumed pool (D-051): the formats that a pool not managed makes not
     * sellable (mockup "Magazzino").
     *
     * @return array<int, list<string>>
     */
    private function formatsByPool(): array
    {
        $result = [];

        foreach ($this->grid->ledFormats()->load('pools') as $format) {
            foreach ($format->pools as $pool) {
                $result[$pool->id][] = $format->name;
            }
        }

        return $result;
    }
}
