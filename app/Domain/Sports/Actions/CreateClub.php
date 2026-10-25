<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Data\ClubData;
use App\Domain\Sports\Data\SportsAuditValues;
use App\Enums\AuditAction;
use App\Models\Club;
use App\Models\Discipline;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * New club (M02 F-05): admin only (D-137). The logo is uploaded afterwards from the page of the
 * club (ChangeClubLogo). Audit club.created with every field.
 */
final class CreateClub
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(ClubData $data, User $actor): Club
    {
        return DB::transaction(function () use ($data, $actor): Club {
            $club = Club::query()->create([
                'discipline_id' => Discipline::default()->id,
                'code' => $data->code,
                ...self::attributes($data),
            ]);

            $this->audit->record(AuditAction::ClubCreated, $actor, AuditSubject::club($club), [], SportsAuditValues::club($club));

            return $club;
        });
    }

    /**
     * Fields set from the form, the code excluded (stable, C3).
     *
     * @return array<string, mixed>
     */
    public static function attributes(ClubData $data): array
    {
        return [
            'name' => $data->name,
            'short_name' => $data->shortName,
            'home_stadium_id' => $data->homeStadiumId,
            'primary_color' => $data->primaryColor,
            'secondary_color' => $data->secondaryColor,
            'is_infront' => $data->isInfront,
            'is_top_match_opponent' => $data->isTopMatchOpponent,
            'is_active' => $data->isActive,
        ];
    }
}
