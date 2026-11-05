<?php

namespace App\Console\Commands;

use App\Domain\Inventory\Data\ResourceAvailability;
use App\Domain\Inventory\Data\SellableOption;
use App\Domain\Inventory\Exceptions\InventoryActionNotAllowed;
use App\Domain\Inventory\Services\InventoryAvailability;
use App\Domain\Inventory\Services\SellableOptions;
use App\Enums\PriceKind;
use App\Models\Club;
use App\Models\Discipline;
use App\Models\Season;
use App\Support\Amounts;
use App\Support\Inventory\InventoryPresenter;
use App\Support\LedDuration;
use App\Support\Text;
use Illuminate\Console\Command;

/**
 * Read-only check of the inventory of a club from the server (D-337 point 7; slice F2-05), until the
 * configurator uses it (F4): availability of every resource (capacity, committed, residual) and the
 * options the configurator can sell (SellableOptions). Nothing is written.
 *
 *   php artisan inventory:availability 2027-28 LAZIO
 */
final class InventoryAvailabilityCommand extends Command
{
    protected $signature = 'inventory:availability
        {season : Code of the season, e.g. 2027-28}
        {club : Code of the club, e.g. LAZIO}';

    protected $description = 'Shows the availability and the sellable options of a club in a season (read only)';

    public function handle(InventoryAvailability $availability, SellableOptions $sellable): int
    {
        $seasonCode = $this->argument('season');
        $season = Season::query()->where('discipline_id', Discipline::default()->id)->where('code', $seasonCode)->first();

        if ($season === null) {
            $this->error(Text::get('inventory.console.unknown_season', ['season' => $seasonCode]));

            return self::FAILURE;
        }

        $clubCode = $this->argument('club');
        $club = Club::query()->where('code', mb_strtoupper($clubCode))->first();

        if ($club === null) {
            $this->error(Text::get('inventory.console.unknown_club', ['club' => $clubCode]));

            return self::FAILURE;
        }

        try {
            $resources = $availability->forClub($season, $club);
            $result = $sellable->forClub($season, $club);
        } catch (InventoryActionNotAllowed $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($result->reason === null
            ? Text::get('inventory.console.selectable', ['club' => $club->name, 'season' => $season->code])
            : Text::get('inventory.console.not_selectable', ['club' => $club->name, 'season' => $season->code, 'reason' => $result->reason->label()]));

        $this->line(Text::get('inventory.console.availability'));
        $this->table(
            array_map(fn (string $key): string => Text::get('inventory.console.'.$key), ['resource', 'value', 'managed', 'capacity', 'committed', 'remaining']),
            array_map(fn (ResourceAvailability $item): array => [
                Text::get('inventory.resources.'.$item->resource->value),
                $item->name,
                Text::get(match (true) {
                    ! $item->isConfigured => 'inventory.console.not_configured',
                    $item->isEnabled => 'inventory.console.yes',
                    default => 'inventory.console.no',
                }),
                $item->isConfigured ? InventoryPresenter::quantity($item->resource, $item->capacity) : '—',
                InventoryPresenter::quantity($item->resource, $item->committed),
                $item->isConfigured ? InventoryPresenter::quantity($item->resource, $item->remaining()) : '—',
            ], [...$resources['pool'], ...$resources['partnership'], ...$resources['hospitality']]),
        );

        if ($result->reason !== null) {
            return self::SUCCESS;
        }

        $this->line(Text::get('inventory.console.options'));
        $this->table(
            array_map(fn (string $key): string => Text::get('inventory.console.'.$key), ['kind', 'option', 'status', 'remaining', 'list_price']),
            array_map(fn (SellableOption $option): array => [
                Text::get('inventory.console.kinds.'.$option->kind->value),
                $option->name,
                $option->status->label(),
                $option->remaining === null ? '—' : self::remaining($option),
                match (true) {
                    $option->isFreePackage => Text::get('inventory.console.free_package'),
                    $option->listPrice === null => '—',
                    default => Amounts::italian($option->listPrice).' €',
                },
            ], $result->options()),
        );

        return self::SUCCESS;
    }

    private static function remaining(SellableOption $option): string
    {
        $remaining = (int) $option->remaining;

        return $option->kind === PriceKind::Led ? LedDuration::format($remaining) : (string) $remaining;
    }
}
