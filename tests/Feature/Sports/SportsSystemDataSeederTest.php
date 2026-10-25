<?php

use App\Domain\Sports\Actions\UpdateCompetition;
use App\Domain\Sports\Data\CompetitionData;
use App\Enums\CompetitionType;
use App\Models\AuditEvent;
use App\Models\Competition;
use App\Models\Discipline;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;

/*
| System data of the sports registry (progettazione-tecnica §5.6.1 A1; D-012, D-016, D-321): the
| discipline football and Serie A, Serie B, Serie C groups, run again at every release (D-295).
*/

it('D-321: seeds football and the competitions of the initial perimeter, by the system', function () {
    $this->seed(SystemDataSeeder::class);

    expect(Discipline::query()->pluck('code')->all())->toBe(['CALCIO'])
        ->and(Competition::query()->orderBy('sort_order')->get()->map(fn (Competition $c): array => [$c->code, $c->name, $c->tier, $c->has_top_match_kpi, $c->type])->all())
        ->toBe([
            ['SERIE_A', 'Serie A', 1, true, CompetitionType::League],
            ['SERIE_B', 'Serie B', 2, false, CompetitionType::League],
            ['SERIE_C_GA', 'Serie C – Girone A', 3, false, CompetitionType::League],
            ['SERIE_C_GB', 'Serie C – Girone B', 3, false, CompetitionType::League],
            ['SERIE_C_GC', 'Serie C – Girone C', 3, false, CompetitionType::League],
        ])
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('D-295: a second run keeps the changes of the admin and adds only what is missing', function () {
    $this->seed(SystemDataSeeder::class);
    $serieC = Competition::query()->where('code', 'SERIE_C_GC')->firstOrFail();
    app(UpdateCompetition::class)->handle($serieC, new CompetitionData(
        code: 'SERIE_C_GC', name: 'Serie C Sud', shortName: 'C Sud', type: CompetitionType::League,
        tier: 3, hasTopMatchKpi: false, isActive: false, sortOrder: 50,
    ), User::factory()->admin()->create());
    Competition::query()->where('code', 'SERIE_B')->delete();

    $this->seed(SystemDataSeeder::class);

    $serieC->refresh();
    expect($serieC->name)->toBe('Serie C Sud')
        ->and($serieC->is_active)->toBeFalse()
        ->and(Competition::query()->where('code', 'SERIE_B')->exists())->toBeTrue()
        ->and(Competition::query()->count())->toBe(5)
        ->and(Discipline::query()->count())->toBe(1);
});
