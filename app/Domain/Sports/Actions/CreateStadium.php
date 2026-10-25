<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Data\SportsAuditValues;
use App\Domain\Sports\Data\StadiumData;
use App\Enums\AuditAction;
use App\Models\Stadium;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * New stadium (M02 F-07): admin and manager (D-137). Audit stadium.created with every field.
 */
final class CreateStadium
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(StadiumData $data, User $actor): Stadium
    {
        return DB::transaction(function () use ($data, $actor): Stadium {
            $stadium = Stadium::query()->create(self::attributes($data));

            $this->audit->record(AuditAction::StadiumCreated, $actor, AuditSubject::stadium($stadium), [], SportsAuditValues::stadium($stadium));

            return $stadium;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function attributes(StadiumData $data): array
    {
        return [
            'name' => $data->name,
            'city' => $data->city,
            'capacity' => $data->capacity,
            'is_active' => $data->isActive,
        ];
    }
}
