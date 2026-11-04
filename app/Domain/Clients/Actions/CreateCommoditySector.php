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
use Illuminate\Support\Str;

/**
 * New commodity sector (M05 F-05; D-357): admin only. The code comes from the name and then never
 * changes (C3): capital letters without accents, other characters as "_", a number at the end when
 * the code is already used. A new sector never asks for a free text: only ALTRO_VARIE does (RB-04).
 * Audit commodity_sector.created with the code and every field.
 */
final class CreateCommoditySector
{
    private const CODE_LENGTH = 32;

    public function __construct(private readonly AuditLog $audit) {}

    public function handle(CommoditySectorData $data, User $actor): CommoditySector
    {
        return DB::transaction(function () use ($data, $actor): CommoditySector {
            $sector = CommoditySector::query()->create([
                'code' => self::codeFor($data->name),
                'name' => $data->name,
                'requires_free_text' => false,
                'is_active' => $data->isActive,
                'sort_order' => $data->sortOrder,
            ]);

            $this->audit->record(
                AuditAction::CommoditySectorCreated,
                $actor,
                AuditSubject::commoditySector($sector),
                [],
                ['code' => $sector->code] + ClientsAuditValues::sector($sector),
            );

            return $sector;
        });
    }

    /**
     * Free code for a name, e.g. "Sport & Fitness" → SPORT_FITNESS, then SPORT_FITNESS_2.
     */
    public static function codeFor(string $name): string
    {
        $base = trim((string) preg_replace('/[^A-Z0-9]+/', '_', mb_strtoupper(Str::ascii($name))), '_');

        if ($base === '' || ! ctype_alpha($base[0])) {
            $base = 'SETTORE_'.$base;
        }

        $base = rtrim(substr($base, 0, self::CODE_LENGTH), '_');
        $code = $base;

        for ($suffix = 2; CommoditySector::query()->where('code', $code)->exists(); $suffix++) {
            $code = rtrim(substr($base, 0, self::CODE_LENGTH - strlen('_'.$suffix)), '_').'_'.$suffix;
        }

        return $code;
    }
}
