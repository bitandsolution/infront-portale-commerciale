<?php

use App\Enums\AuditAction;
use App\Enums\CompetitionType;
use App\Models\AuditEvent;
use App\Models\Competition;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Database\Factories\DisciplineFactory;
use Database\Seeders\SystemDataSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Competitions page (M02 F-02; D-137: admin only; C3 stable code, C4 no deletion). Permissions of
| every route are in the route × role matrix (RouteMatrixTest).
*/

// The discipline of the system data (CALCIO), always present in every environment (D-321).
beforeEach(fn () => DisciplineFactory::football());

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sportsCompetitionForm(array $overrides = []): array
{
    return array_merge([
        'code' => 'SERIE_C_GD',
        'name' => 'Serie C – Girone D',
        'short_name' => 'Serie C D',
        'type' => 'league',
        'tier' => 3,
        'has_top_match_kpi' => false,
        'is_active' => true,
        'sort_order' => 60,
    ], $overrides);
}

it('F-02: the admin sees the competitions in the order of the lists', function () {
    $this->seed(SystemDataSeeder::class);

    $this->actingAs(User::factory()->admin()->create())
        ->get(PortalPages::url('sports_competitions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sports/Competitions/Index')
            ->has('competitions', 5)
            ->where('competitions.0.code', 'SERIE_A')
            ->where('competitions.0.typeLabel', 'Campionato')
            ->where('competitions.0.hasTopMatchKpi', true)
            ->where('competitions.4.name', 'Serie C – Girone C'));
});

it('F-02, D-127: creates a competition, the code in capital letters, with an audit event', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('sports.competitions.store'), sportsCompetitionForm(['code' => ' serie_c_gd ']))
        ->assertRedirect(PortalPages::url('sports_competitions'))
        ->assertSessionHas('toast.message', 'Competizione Serie C – Girone D creata.');

    $competition = Competition::query()->sole();
    expect($competition->code)->toBe('SERIE_C_GD')
        ->and($competition->type)->toBe(CompetitionType::League)
        ->and($competition->tier)->toBe(3)
        ->and($competition->discipline->code)->toBe('CALCIO');

    $event = soleAuditEvent(AuditAction::CompetitionCreated);
    expect($event->area)->toBe('sports')
        ->and($event->object_label)->toBe('Serie C – Girone D (SERIE_C_GD)')
        ->and($event->new_values)->toMatchArray(['code' => 'SERIE_C_GD', 'tier' => 3, 'is_active' => true]);
});

it('F-02: validates the fields of a competition', function (array $values, string $field, string $message) {
    Competition::factory()->create(['code' => 'GIA_USATO']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.competitions.store'), sportsCompetitionForm($values))
        ->assertSessionHasErrors([$field => $message]);

    expect(Competition::query()->count())->toBe(1);
})->with([
    'code with spaces' => [['code' => 'SERIE C'], 'code', 'Il formato del campo codice non è valido.'],
    'code already used' => [['code' => 'gia_usato'], 'code', 'Il valore del campo codice è già in uso.'],
    'tier zero' => [['tier' => 0], 'tier', 'Il campo livello deve essere compreso tra 1 e 9.'],
    'unknown type' => [['type' => 'friendly'], 'type', 'Il valore scelto per tipo non è valido.'],
    'short name too long' => [['short_name' => str_repeat('x', 41)], 'short_name', 'Il campo nome breve non può superare 40 caratteri.'],
]);

it('C3, C4: changes and deactivates a competition, the code stays, the audit has only the changes', function () {
    $competition = Competition::factory()->create(['code' => 'SERIE_C_GD', 'name' => 'Serie C – Girone D', 'short_name' => 'Serie C D', 'sort_order' => 60]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('sports.competitions.update', $competition), sportsCompetitionForm(['code' => 'ALTRO', 'is_active' => false]))
        ->assertSessionHas('toast.message', 'Competizione Serie C – Girone D salvata.');

    $competition->refresh();
    expect($competition->code)->toBe('SERIE_C_GD')
        ->and($competition->is_active)->toBeFalse();

    $event = soleAuditEvent(AuditAction::CompetitionUpdated);
    expect($event->old_values)->toBe(['is_active' => true])
        ->and($event->new_values)->toBe(['is_active' => false]);
});

it('F-02: saving the same values writes nothing', function () {
    $competition = Competition::factory()->create(['code' => 'SERIE_C_GD', 'name' => 'Serie C – Girone D', 'short_name' => 'Serie C D', 'sort_order' => 60]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('sports.competitions.update', $competition), sportsCompetitionForm())
        ->assertSessionHas('toast.message', 'Nessuna modifica da salvare.');

    expect(AuditEvent::query()->count())->toBe(0);
});
