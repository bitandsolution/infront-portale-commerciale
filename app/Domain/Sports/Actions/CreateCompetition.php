<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Data\CompetitionData;
use App\Domain\Sports\Data\SportsAuditValues;
use App\Enums\AuditAction;
use App\Models\Competition;
use App\Models\Discipline;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * New competition (M02 F-02): admin only (D-137). Serie A, Serie B and the Serie C groups come from
 * the system data (D-321); the page serves new groups or competitions.
 */
final class CreateCompetition
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(CompetitionData $data, User $actor): Competition
    {
        return DB::transaction(function () use ($data, $actor): Competition {
            $competition = Competition::query()->create([
                'discipline_id' => Discipline::default()->id,
                'code' => $data->code,
                ...self::attributes($data),
            ]);

            $this->audit->record(
                AuditAction::CompetitionCreated,
                $actor,
                AuditSubject::competition($competition),
                [],
                SportsAuditValues::competition($competition),
            );

            return $competition;
        });
    }

    /**
     * Fields set from the form, the code excluded (stable, C3).
     *
     * @return array<string, mixed>
     */
    public static function attributes(CompetitionData $data): array
    {
        return [
            'name' => $data->name,
            'short_name' => $data->shortName,
            'type' => $data->type,
            'tier' => $data->tier,
            'has_top_match_kpi' => $data->hasTopMatchKpi,
            'is_active' => $data->isActive,
            'sort_order' => $data->sortOrder,
        ];
    }
}
