<?php

use App\Domain\Sports\Actions\CreateSeason;
use App\Domain\Sports\Data\SeasonData;
use App\Enums\AuditAction;
use App\Enums\SeasonStatus;
use App\Models\AuditEvent;
use App\Models\Competition;
use App\Models\Season;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Seasons page (M02 F-03, §8; D-137: admin only; D-317). Permissions of every route are in the
| route × role matrix (RouteMatrixTest).
*/

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sportsSeasonForm(array $overrides = []): array
{
    return array_merge([
        'code' => '2027-28',
        'name' => 'Stagione 2027-28',
        'starts_on' => '2027-07-01',
        'ends_on' => '2028-06-30',
        'status' => 'planned',
    ], $overrides);
}

it('F-03: the admin sees the seasons, the most recent first, with period, status and competitions', function () {
    $older = Season::factory()->forYear(2026)->closed()->create();
    $newer = Season::factory()->forYear(2027)->open()->create();
    $newer->competitionSeasons()->create(['competition_id' => Competition::factory()->create()->id, 'standard_home_matches' => 19]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(PortalPages::url('sports_seasons'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sports/Seasons/Index')
            ->where('layout.title', 'Stagioni')
            ->has('seasons', 2)
            ->where('seasons.0.code', '2027-28')
            ->where('seasons.0.startsOn', '01/07/2027')
            ->where('seasons.0.endsOn', '30/06/2028')
            ->where('seasons.0.statusLabel', 'Aperta')
            ->where('seasons.0.competitions', 1)
            ->where('seasons.0.url', route('sports.seasons.edit', $newer))
            ->where('seasons.1.code', $older->code)
            ->where('seasons.1.statusLabel', 'Chiusa'));
});

it('F-03, D-127: creates a season with an audit event carrying every field', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('sports.seasons.store'), sportsSeasonForm());

    $season = Season::query()->sole();
    $response->assertRedirect(route('sports.seasons.edit', $season))
        ->assertSessionHas('toast.message', 'Stagione 2027-28 creata. Ora aggiungi le competizioni con il numero standard di partite.');

    expect($season->code)->toBe('2027-28')
        ->and($season->starts_on->toDateString())->toBe('2027-07-01')
        ->and($season->status)->toBe(SeasonStatus::Planned)
        ->and($season->discipline->code)->toBe('CALCIO');

    $event = soleAuditEvent(AuditAction::SeasonCreated);
    expect($event->area)->toBe('sports')
        ->and($event->object_type)->toBe('season')
        ->and($event->object_id)->toBe($season->id)
        ->and($event->object_label)->toBe('Stagione 2027-28 (2027-28)')
        ->and($event->user_id)->toBe($admin->id)
        ->and($event->old_values)->toBeNull()
        ->and($event->new_values)->toBe([
            'code' => '2027-28', 'name' => 'Stagione 2027-28', 'starts_on' => '2027-07-01',
            'ends_on' => '2028-06-30', 'status' => 'planned',
        ]);
});

it('M02 §8: refuses a season that overlaps another one, saving nothing', function () {
    Season::factory()->forYear(2027)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('sports.seasons.create'))
        ->post(route('sports.seasons.store'), sportsSeasonForm(['code' => '2028-29', 'starts_on' => '2028-06-30', 'ends_on' => '2029-06-30']))
        ->assertRedirect(route('sports.seasons.create'))
        ->assertSessionHas('toast.message', 'Le date si sovrappongono alla stagione 2027-28: due stagioni non possono coprire gli stessi giorni.');

    expect(Season::query()->count())->toBe(1)
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('M02 §8: validates code, dates and status', function (array $values, string $field, string $message) {
    Season::factory()->forYear(2030)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('sports.seasons.create'))
        ->post(route('sports.seasons.store'), sportsSeasonForm($values))
        ->assertSessionHasErrors([$field => $message]);

    expect(Season::query()->count())->toBe(1);
})->with([
    'code format' => [['code' => '2027/28'], 'code', 'Il formato del campo codice non è valido.'],
    'years not consecutive' => [['code' => '2027-29'], 'code', 'Il codice deve indicare due anni consecutivi, per esempio 2027-28.'],
    'code already used' => [['code' => '2030-31', 'starts_on' => '2040-07-01', 'ends_on' => '2041-06-30'], 'code', 'Il valore del campo codice è già in uso.'],
    'end before start' => [['ends_on' => '2027-06-01'], 'ends_on', 'Il campo data di fine deve essere una data successiva a data di inizio.'],
    'unknown status' => [['status' => 'archived'], 'status', 'Il valore scelto per stato non è valido.'],
    'empty name' => [['name' => ''], 'name', 'Il campo nome è obbligatorio.'],
]);

it('D-317: changes name, dates and status with only the changed fields in the audit; the code never changes', function () {
    $admin = User::factory()->admin()->create();
    $season = Season::factory()->forYear(2027)->create();

    $this->actingAs($admin)
        ->put(route('sports.seasons.update', $season), sportsSeasonForm(['code' => '2099-00', 'name' => 'Stagione sportiva 2027-28', 'status' => 'open']))
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'Stagione 2027-28 salvata.');

    $season->refresh();
    expect($season->code)->toBe('2027-28')
        ->and($season->name)->toBe('Stagione sportiva 2027-28')
        ->and($season->status)->toBe(SeasonStatus::Open);

    $event = soleAuditEvent(AuditAction::SeasonUpdated);
    expect($event->old_values)->toBe(['name' => 'Stagione 2027-28', 'status' => 'planned'])
        ->and($event->new_values)->toBe(['name' => 'Stagione sportiva 2027-28', 'status' => 'open']);
});

it('D-317: saving the same values writes nothing and says so', function () {
    $season = Season::factory()->forYear(2027)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('sports.seasons.update', $season), sportsSeasonForm())
        ->assertSessionHas('toast.message', 'Nessuna modifica da salvare.');

    expect(AuditEvent::query()->count())->toBe(0);
});

it('M02 §8: a season may keep its own dates, but not move onto another season', function () {
    Season::factory()->forYear(2028)->create();
    $season = Season::factory()->forYear(2027)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('sports.seasons.update', $season), sportsSeasonForm(['ends_on' => '2028-07-15']))
        ->assertSessionHas('toast.message', 'Le date si sovrappongono alla stagione 2028-29: due stagioni non possono coprire gli stessi giorni.');

    expect($season->refresh()->ends_on->toDateString())->toBe('2028-06-30');
});

it('D-317: a closed season can be reopened', function () {
    $season = Season::factory()->forYear(2027)->closed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('sports.seasons.update', $season), sportsSeasonForm(['status' => 'open']))
        ->assertSessionHas('toast.message', 'Stagione 2027-28 salvata.');

    expect($season->refresh()->status)->toBe(SeasonStatus::Open);
});

it('D-284: an audit event that cannot be written cancels the creation (fail-closed)', function () {
    AuditEvent::creating(fn () => throw new RuntimeException('audit non scrivibile'));

    expect(fn () => app(CreateSeason::class)->handle(new SeasonData(
        code: '2027-28', name: 'Stagione 2027-28', startsOn: Carbon::parse('2027-07-01'),
        endsOn: Carbon::parse('2028-06-30'), status: SeasonStatus::Planned,
    ), User::factory()->admin()->create()))->toThrow(RuntimeException::class);

    expect(Season::query()->count())->toBe(0);
});

it('F-03: the page of a season shows its data, its competitions and the ones that can be added', function () {
    $this->seed(SystemDataSeeder::class);
    $season = Season::factory()->forYear(2027)->create();
    $serieA = Competition::query()->where('code', 'SERIE_A')->firstOrFail();
    $season->competitionSeasons()->create(['competition_id' => $serieA->id, 'standard_home_matches' => 19]);
    Competition::query()->where('code', 'SERIE_C_GC')->update(['is_active' => false]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('sports.seasons.edit', $season))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sports/Seasons/Form')
            ->where('season.code', '2027-28')
            ->where('season.startsOn', '2027-07-01')
            ->where('season.readOnly', false)
            ->has('editions', 1)
            ->where('editions.0.competitionName', 'Serie A')
            ->where('editions.0.standardHomeMatches', 19)
            ->where('editions.0.calendarLoadedAt', null)
            ->where('availableCompetitions', fn ($options): bool => collect($options)->pluck('label')->all() === ['Serie B', 'Serie C – Girone A', 'Serie C – Girone B'])
            ->where('addEditionUrl', route('sports.editions.store', $season)));
});
