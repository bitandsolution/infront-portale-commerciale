<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Catalog\Services\PriceListGrid;
use App\Domain\Inventory\Data\CapacityInput;
use App\Domain\Inventory\Data\ResourceAvailability;
use App\Domain\Inventory\Exceptions\CapacityBelowCommitted;
use App\Domain\Inventory\Exceptions\InventoryActionNotAllowed;
use App\Domain\Inventory\Services\InventoryAvailability;
use App\Domain\Settings\Services\Settings;
use App\Enums\InventoryResource;
use App\Models\Club;
use App\Models\Season;
use App\Models\User;
use App\Support\LedDuration;
use Illuminate\Support\Facades\DB;

/**
 * Capacities of a club in a season, saved together from the page "Magazzino › Capacità" (M04 F-01…
 * F-03, FL-01; D-062, D-066, D-072, D-191, D-337): admin only, configuration only by hand (D-067).
 *
 * - For every active resource: flag "managed" and quantity; LED pools in multiples of the step of the
 *   settings (D-063, D-292). A missing row stays missing when the admin leaves it not managed at 0.
 * - Capacities under the committed quantity (or a resource no longer managed with commitments) are
 *   allowed only with the confirmation (RB-10, D-072): without it nothing is written and
 *   CapacityBelowCommitted lists them; the residual stays negative and highlighted.
 * - Refused in a closed season and with an availability method other than "seasonal" (D-293).
 * - The capacity rows are locked, so an approval of F5 sees either the old or the new capacities;
 *   one audit event <resource>_capacity.updated for every row changed, with only
 *   the changed fields.
 */
final class SetClubCapacities
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly InventoryAvailability $availability,
        private readonly PriceListGrid $grid,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  list<CapacityInput>  $inputs
     * @return array{changed: int, belowCommitted: list<ResourceAvailability>}
     */
    public function handle(Season $season, Club $club, array $inputs, bool $confirmed, User $actor): array
    {
        $this->availability->ensureSeasonal();

        return DB::transaction(function () use ($season, $club, $inputs, $confirmed, $actor): array {
            $season = Season::query()->lockForUpdate()->findOrFail($season->id);

            if ($season->isReadOnly()) {
                throw InventoryActionNotAllowed::seasonClosed($season->code);
            }

            if (! $this->grid->hasClub($season, $club)) {
                throw InventoryActionNotAllowed::clubNotInGrid($club->name, $season->code);
            }

            $step = $this->settings->ledStepSeconds();
            $changes = [];
            $belowCommitted = [];

            foreach ($inputs as $input) {
                $value = $this->availability->values($input->resource)->firstWhere('id', $input->valueId);

                if ($value === null) {
                    $valueModel = $input->resource->valueModel();
                    $name = $valueModel::query()->find($input->valueId)?->getAttribute('name');

                    throw InventoryActionNotAllowed::valueNotManaged(is_string($name) ? $name : (string) $input->valueId);
                }

                if ($input->resource === InventoryResource::Pool && $input->quantity % $step !== 0) {
                    throw InventoryActionNotAllowed::stepNotRespected($value->name, LedDuration::format($step));
                }

                $model = $input->resource->capacityModel();
                $quantityColumn = $input->resource->quantityColumn();
                $row = $model::query()
                    ->where('season_id', $season->id)
                    ->where('club_id', $club->id)
                    ->where($input->resource->valueKey(), $value->id)
                    ->lockForUpdate()
                    ->first();

                $before = ['is_enabled' => (bool) $row?->getAttribute('is_enabled'), $quantityColumn => (int) $row?->getAttribute($quantityColumn)];
                $after = ['is_enabled' => $input->isEnabled, $quantityColumn => $input->quantity];

                if ($before === $after) {
                    continue;
                }

                $committed = $this->availability->committed($season, $club, $input->resource, $value->id);
                $resulting = new ResourceAvailability($input->resource, $value->id, $value->code, $value->name, true, $input->isEnabled, $input->quantity, $committed);

                if ($resulting->isBelowCommitted() || (! $input->isEnabled && $committed > 0)) {
                    $belowCommitted[] = $resulting;
                }

                $changes[] = [$input, $value, $row, $before, $after];
            }

            if ($belowCommitted !== [] && ! $confirmed) {
                throw new CapacityBelowCommitted($belowCommitted);
            }

            foreach ($changes as [$input, $value, $row, $before, $after]) {
                $model = $input->resource->capacityModel();

                if ($row === null) {
                    $row = $model::query()->create([
                        'season_id' => $season->id,
                        'club_id' => $club->id,
                        $input->resource->valueKey() => $value->id,
                        ...$after,
                    ]);
                    [$old, $new] = [[], $after];
                } else {
                    $row->fill($after)->save();
                    [$old, $new] = AuditLog::changes($before, $after);
                }

                $this->audit->record(
                    $input->resource->auditAction(),
                    $actor,
                    AuditSubject::capacity($row->id, $season, $club, $value->name.' ('.$value->code.')'),
                    $old,
                    $new,
                );
            }

            return ['changed' => count($changes), 'belowCommitted' => $belowCommitted];
        });
    }
}
