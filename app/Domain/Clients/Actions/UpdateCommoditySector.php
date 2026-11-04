<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Clients\Data\ClientsAuditValues;
use App\Domain\Clients\Data\CommoditySectorData;
use App\Enums\AuditAction;
use App\Models\CommoditySector;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Change of a commodity sector (M05 F-05; D-357): name, order and active, admin only. Never deleted:
 * a sector used by clients is deactivated (RB-08) and is no longer offered for new clients. The code
 * and the free-text flag never change. Audit with only the changed fields.
 */
final class UpdateCommoditySector
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(CommoditySector $sector, CommoditySectorData $data, User $actor): bool
    {
        return DB::transaction(function () use ($sector, $data, $actor): bool {
            $sector = CommoditySector::query()->lockForUpdate()->findOrFail($sector->id);
            $before = ClientsAuditValues::sector($sector);

            $sector->fill([
                'name' => $data->name,
                'sort_order' => $data->sortOrder,
                'is_active' => $data->isActive,
            ]);

            [$old, $new] = AuditLog::changes($before, ClientsAuditValues::sector($sector));

            if ($new === []) {
                return false;
            }

            $sector->save();

            $this->audit->record(AuditAction::CommoditySectorUpdated, $actor, AuditSubject::commoditySector($sector), $old, $new);

            return true;
        });
    }
}
