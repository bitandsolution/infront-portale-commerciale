<?php

use App\Domain\Content\Actions\SetClubKpis;
use App\Domain\Content\Data\KpiInput;
use App\Domain\Content\Exceptions\ContentActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\KpiGroup;
use App\Http\Requests\Content\ClubKpisRequest;
use App\Models\AuditEvent;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\ClubKpiValue;
use App\Models\CompetitionSeason;
use App\Models\KpiDefinition;
use App\Models\Season;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Database\Seeders\ContentSystemDataSeeder;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
| KPI and digital of the clubs (M08 F-05, F-08, §5, §9; D-108, D-109, D-203, D-490…D-492; slice
| F6-01): definitions from the system data, pages, save with source and period, removal, audit,
| warning of the KPI not updated for more than 6 months, closed season, database constraints.
| Permissions of every route are in the route × role matrix (RouteMatrixTest).
*/

beforeEach(function () {
    $this->seed(SystemDataSeeder::class);
    $this->travelTo(Carbon::parse('2027-03-01 10:00:00'));
    $this->season = Season::factory()->create();
});

/**
 * An Infront club enrolled in a competition of the season (PriceListGrid, D-341).
 */
function kpisClub(Season $season, string $name = 'SS Lazio'): Club
{
    $club = Club::factory()->infront()->create(['name' => $name]);
    ClubEnrollment::query()->create(['competition_season_id' => CompetitionSeason::factory()->for($season)->create()->id, 'club_id' => $club->id]);

    return $club;
}

function kpisDefinition(string $code): KpiDefinition
{
    return KpiDefinition::query()->where('code', $code)->firstOrFail();
}

/**
 * @param  array<string, array{value?: string, source?: string, period?: string}>  $kpis  code → fields
 * @return array<string, mixed>
 */
function kpisForm(Season $season, array $kpis): array
{
    $rows = [];

    foreach ($kpis as $code => $fields) {
        $rows[(string) kpisDefinition($code)->id] = array_merge(['value' => '', 'source' => '', 'period' => ''], $fields);
    }

    return ['season_id' => $season->id, 'kpis' => $rows];
}

function kpisValue(Club $club, Season $season, string $code): ?ClubKpiValue
{
    return ClubKpiValue::query()
        ->where('club_id', $club->id)
        ->where('season_id', $season->id)
        ->where('kpi_definition_id', kpisDefinition($code)->id)
        ->first();
}

it('D-490: the seeder creates the 13 KPI of D-108, club and digital, with the network sums, and is idempotent', function () {
    (new ContentSystemDataSeeder)->run();

    expect(KpiDefinition::query()->count())->toBe(13)
        ->and(KpiDefinition::query()->where('group', KpiGroup::Club->value)->count())->toBe(6)
        ->and(KpiDefinition::query()->where('group', KpiGroup::Digital->value)->count())->toBe(7)
        ->and(KpiDefinition::query()->where('network_aggregate', true)->orderBy('code')->pluck('code')->all())->toBe([
            'ANNUAL_ATTENDANCE', 'FACEBOOK_FOLLOWERS', 'FANBASE_ITALY', 'HOME_AUDIENCE', 'INSTAGRAM_FOLLOWERS',
            'TIKTOK_FOLLOWERS', 'X_FOLLOWERS', 'YOUTUBE_FOLLOWERS',
        ])
        ->and(KpiDefinition::query()->activeOrdered()->first()?->code)->toBe('FANBASE_ITALY');
});

it('F-05: admin and manager see the Infront clubs of the season with how many KPI they have', function (string $role) {
    $lazio = kpisClub($this->season, 'SS Lazio');
    kpisClub($this->season, 'AS Roma');
    Club::factory()->infront()->create(['name' => 'Club non iscritto']);
    $admin = User::factory()->admin()->create();
    app(SetClubKpis::class)->handle($this->season, $lazio, [
        new KpiInput(kpisDefinition('FANBASE_ITALY')->id, 1800000, 'Nielsen', 'Stagione 2026-27'),
        new KpiInput(kpisDefinition('SEASON_TICKETS')->id, 30000, 'Club', 'Stagione 2026-27'),
    ], $admin);

    $this->actingAs(User::factory()->{$role}()->create())
        ->get(PortalPages::url('content_kpi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Content/Kpis/Index')
            ->where('page.season.code', $this->season->code)
            ->where('page.total', 13)
            ->where('page.staleMonths', 6)
            ->has('page.rows', 2)
            ->where('page.rows.0.name', 'AS Roma')
            ->where('page.rows.0.present', 0)
            ->where('page.rows.0.lastUpdate', null)
            ->where('page.rows.1.name', 'SS Lazio')
            ->where('page.rows.1.present', 2)
            ->where('page.rows.1.stale', 0)
            ->where('page.rows.1.lastUpdate', '01/03/2027')
            ->where('page.rows.1.editUrl', route('content.kpis.edit', [$lazio, 'stagione' => $this->season->id])));
})->with(['admin', 'manager']);

it('F-05: the form of a club shows the KPI club and digital with value, source, period and update', function () {
    $lazio = kpisClub($this->season);
    $manager = User::factory()->manager()->create(['first_name' => 'Marta', 'last_name' => 'Gestori']);
    app(SetClubKpis::class)->handle($this->season, $lazio, [
        new KpiInput(kpisDefinition('FANBASE_ITALY')->id, 1800000, 'Nielsen', 'Stagione 2026-27'),
    ], $manager);

    $this->actingAs($manager)
        ->get(route('content.kpis.edit', [$lazio, 'stagione' => $this->season->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Content/Kpis/Edit')
            ->where('club.name', 'SS Lazio')
            ->has('groups', 2)
            ->where('groups.0.group', 'club')
            ->where('groups.0.label', 'KPI club')
            ->has('groups.0.rows', 6)
            ->where('groups.1.group', 'digital')
            ->has('groups.1.rows', 7)
            ->where('groups.0.rows.0.name', 'Fanbase Italia')
            ->where('groups.0.rows.0.value', '1.800.000')
            ->where('groups.0.rows.0.source', 'Nielsen')
            ->where('groups.0.rows.0.period', 'Stagione 2026-27')
            ->where('groups.0.rows.0.updatedAt', '01/03/2027')
            ->where('groups.0.rows.0.updatedBy', 'Marta Gestori')
            ->where('groups.0.rows.0.networkAggregate', true)
            ->where('groups.0.rows.1.value', '')
            ->where('groups.0.rows.1.updatedAt', null));
});

it('F-05, D-491: saves values with source and period, typed with the separators of the thousands, with one audit event', function () {
    $lazio = kpisClub($this->season);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('content.kpis.edit', $lazio))
        ->put(route('content.kpis.update', $lazio), kpisForm($this->season, [
            'FANBASE_ITALY' => ['value' => '1.800.000', 'source' => ' Nielsen ', 'period' => 'Stagione 2026-27'],
            'X_FOLLOWERS' => ['value' => '600 000', 'source' => 'X', 'period' => 'Ottobre 2027'],
            'DEM_CONTACTS' => ['value' => '', 'source' => 'ignorata', 'period' => ''],
        ]))
        ->assertRedirect(route('content.kpis.edit', $lazio))
        ->assertSessionHas('toast.message', 'KPI di SS Lazio salvati (2 modificati)');

    $fanbase = kpisValue($lazio, $this->season, 'FANBASE_ITALY');
    expect($fanbase?->value)->toBe(1800000)
        ->and($fanbase?->source)->toBe('Nielsen')
        ->and($fanbase?->updated_by)->toBe($admin->id)
        ->and(kpisValue($lazio, $this->season, 'X_FOLLOWERS')?->value)->toBe(600000)
        ->and(kpisValue($lazio, $this->season, 'DEM_CONTACTS'))->toBeNull();

    $event = AuditEvent::query()->where('action', AuditAction::ClubKpisUpdated->value)->sole();
    expect($event->object_type)->toBe('club_kpis')
        ->and($event->object_label)->toBe('SS Lazio ('.$lazio->code.') · '.$this->season->code)
        ->and($event->old_values)->toBeNull()
        ->and($event->new_values)->toBe([
            'fanbase_italy' => '1800000 · Nielsen · Stagione 2026-27',
            'x_followers' => '600000 · X · Ottobre 2027',
        ]);
});

it('D-491: an unchanged value keeps its date and author; nothing is written when nothing changes', function () {
    $lazio = kpisClub($this->season);
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->manager()->create();
    $form = kpisForm($this->season, [
        'FANBASE_ITALY' => ['value' => '1800000', 'source' => 'Nielsen', 'period' => 'Stagione 2026-27'],
        'SEASON_TICKETS' => ['value' => '30000', 'source' => 'Club', 'period' => 'Stagione 2026-27'],
    ]);
    $this->actingAs($admin)->put(route('content.kpis.update', $lazio), $form);

    $this->travel(2)->months();
    $form['kpis'][(string) kpisDefinition('SEASON_TICKETS')->id]['value'] = '31.000';
    $this->actingAs($manager)->put(route('content.kpis.update', $lazio), $form)
        ->assertSessionHas('toast.message', 'KPI di SS Lazio salvati (1 modificati)');

    expect(kpisValue($lazio, $this->season, 'FANBASE_ITALY')?->updated_at->toDateString())->toBe('2027-03-01')
        ->and(kpisValue($lazio, $this->season, 'FANBASE_ITALY')?->updated_by)->toBe($admin->id)
        ->and(kpisValue($lazio, $this->season, 'SEASON_TICKETS')?->updated_at->toDateString())->toBe('2027-05-01')
        ->and(kpisValue($lazio, $this->season, 'SEASON_TICKETS')?->updated_by)->toBe($manager->id);

    $event = AuditEvent::query()->where('action', AuditAction::ClubKpisUpdated->value)->latest('id')->first();
    expect($event?->old_values)->toBe(['season_tickets' => '30000 · Club · Stagione 2026-27'])
        ->and($event?->new_values)->toBe(['season_tickets' => '31000 · Club · Stagione 2026-27']);

    $this->actingAs($manager)->put(route('content.kpis.update', $lazio), $form)
        ->assertSessionHas('toast.message', 'Nessuna modifica da salvare');
    expect(AuditEvent::query()->where('action', AuditAction::ClubKpisUpdated->value)->count())->toBe(2);
});

it('D-491: an empty value removes the KPI, recorded in the audit', function () {
    $lazio = kpisClub($this->season);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->put(route('content.kpis.update', $lazio), kpisForm($this->season, [
        'TIKTOK_FOLLOWERS' => ['value' => '900000', 'source' => 'TikTok', 'period' => 'Ottobre 2027'],
    ]));

    $this->actingAs($admin)->put(route('content.kpis.update', $lazio), kpisForm($this->season, [
        'TIKTOK_FOLLOWERS' => ['value' => '', 'source' => 'TikTok', 'period' => 'Ottobre 2027'],
    ]))->assertSessionHas('toast.message', 'KPI di SS Lazio salvati (1 modificati)');

    expect(kpisValue($lazio, $this->season, 'TIKTOK_FOLLOWERS'))->toBeNull();
    $event = AuditEvent::query()->where('action', AuditAction::ClubKpisUpdated->value)->latest('id')->first();
    expect($event?->old_values)->toBe(['tiktok_followers' => '900000 · TikTok · Ottobre 2027'])
        ->and($event?->new_values)->toBe(['tiktok_followers' => null]);
});

it('M08 §9, D-491: refuses decimals, negative numbers and letters, and a value without source or period', function (array $fields, string $field, string $message) {
    $lazio = kpisClub($this->season);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('content.kpis.edit', $lazio))
        ->put(route('content.kpis.update', $lazio), kpisForm($this->season, ['FANBASE_ITALY' => $fields]))
        ->assertRedirect(route('content.kpis.edit', $lazio))
        ->assertSessionHasErrors(['kpis.'.kpisDefinition('FANBASE_ITALY')->id.'.'.$field => $message]);

    expect(ClubKpiValue::query()->count())->toBe(0);
})->with([
    'decimali' => [['value' => '1250,5', 'source' => 'Nielsen', 'period' => '2026-27'], 'value', 'Scrivi un numero intero senza decimali, al massimo di 12 cifre (es. 1.250.000).'],
    'negativo' => [['value' => '-5', 'source' => 'Nielsen', 'period' => '2026-27'], 'value', 'Scrivi un numero intero senza decimali, al massimo di 12 cifre (es. 1.250.000).'],
    'lettere' => [['value' => '29K', 'source' => 'Nielsen', 'period' => '2026-27'], 'value', 'Scrivi un numero intero senza decimali, al massimo di 12 cifre (es. 1.250.000).'],
    'troppe cifre' => [['value' => '1234567890123', 'source' => 'Nielsen', 'period' => '2026-27'], 'value', 'Scrivi un numero intero senza decimali, al massimo di 12 cifre (es. 1.250.000).'],
    'senza fonte' => [['value' => '1000', 'source' => '  ', 'period' => '2026-27'], 'source', 'Scrivi la fonte del valore.'],
    'senza periodo' => [['value' => '1000', 'source' => 'Nielsen', 'period' => ''], 'period', 'Scrivi il periodo di riferimento del valore.'],
]);

it('M08 §9: reads the values typed with the separators of the thousands', function (string $text, ?int $value) {
    expect(ClubKpisRequest::parseValue($text))->toBe($value);
})->with([
    ['1.250.000', 1250000],
    ['1 250 000', 1250000],
    ['0', 0],
    [' 42 ', 42],
    ['', null],
    ['1,5', null],
    ['+3', null],
]);

it('F-08, D-492: a value not updated for more than 6 months is marked, in the list and in the form', function () {
    $lazio = kpisClub($this->season);
    $admin = User::factory()->admin()->create();
    app(SetClubKpis::class)->handle($this->season, $lazio, [
        new KpiInput(kpisDefinition('FANBASE_ITALY')->id, 1800000, 'Nielsen', 'Stagione 2026-27'),
    ], $admin);

    $this->travelTo(Carbon::parse('2027-09-01 10:00:00'));
    $this->actingAs($admin)->get(PortalPages::url('content_kpi'))
        ->assertInertia(fn (Assert $page) => $page->where('page.rows.0.stale', 0));

    $this->travelTo(Carbon::parse('2027-09-02 10:00:00'));
    $this->flushSession();
    $this->actingAs($admin)->get(PortalPages::url('content_kpi'))
        ->assertInertia(fn (Assert $page) => $page->where('page.rows.0.stale', 1));
    $this->actingAs($admin)->get(route('content.kpis.edit', [$lazio, 'stagione' => $this->season->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('groups.0.rows.0.isStale', true)
            ->where('groups.0.rows.1.isStale', false));
});

it('D-317: the KPI of a closed season are read only', function () {
    $closed = Season::factory()->closed()->create();
    $lazio = kpisClub($closed);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('season.select'), ['season_id' => $closed->id]);

    $this->actingAs($admin)->get(PortalPages::url('content_kpi'))
        ->assertInertia(fn (Assert $page) => $page->where('page.season.readOnly', true));

    $this->actingAs($admin)
        ->from(route('content.kpis.edit', [$lazio, 'stagione' => $closed->id]))
        ->put(route('content.kpis.update', $lazio), kpisForm($closed, [
            'FANBASE_ITALY' => ['value' => '1000', 'source' => 'Nielsen', 'period' => '2026-27'],
        ]))
        ->assertSessionHas('toast.message', 'La stagione '.$closed->code.' è chiusa: i KPI sono in sola lettura');

    expect(ClubKpiValue::query()->count())->toBe(0);
});

it('F6-01: only Infront clubs enrolled in the season have KPI', function () {
    $outside = Club::factory()->infront()->create(['name' => 'US Fuori']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(PortalPages::url('content_kpi'))
        ->get(route('content.kpis.edit', [$outside, 'stagione' => $this->season->id]))
        ->assertRedirect(PortalPages::url('content_kpi'))
        ->assertSessionHas('toast.message', 'US Fuori non è un club Infront iscritto a una competizione della stagione '.$this->season->code);

    expect(fn () => app(SetClubKpis::class)->handle($this->season, $outside, [
        new KpiInput(kpisDefinition('FANBASE_ITALY')->id, 1000, 'Nielsen', '2026-27'),
    ], $admin))->toThrow(ContentActionNotAllowed::class);
});

it('F6-01: an inactive KPI cannot be written', function () {
    $lazio = kpisClub($this->season);
    $definition = kpisDefinition('DEM_CONTACTS');
    $definition->update(['is_active' => false]);

    expect(fn () => app(SetClubKpis::class)->handle($this->season, $lazio, [
        new KpiInput($definition->id, 1000, 'Club', '2026-27'),
    ], User::factory()->admin()->create()))->toThrow(ContentActionNotAllowed::class);
});

it('C9: the database refuses two values of the same KPI, an empty source and a negative value', function (Closure $insert) {
    $lazio = kpisClub($this->season);
    $row = ['club_id' => $lazio->id, 'season_id' => $this->season->id, 'kpi_definition_id' => kpisDefinition('FANBASE_ITALY')->id, 'value' => 10, 'source' => 'Fonte', 'reference_period' => '2026-27', 'created_at' => now(), 'updated_at' => now()];

    expect(fn () => $insert($row))->toThrow(QueryException::class);
})->with([
    'doppio' => [function (array $row): void {
        DB::table('club_kpi_values')->insert($row);
        DB::table('club_kpi_values')->insert($row);
    }],
    'fonte vuota' => [fn (array $row) => DB::table('club_kpi_values')->insert(array_merge($row, ['source' => '  ']))],
    'periodo vuoto' => [fn (array $row) => DB::table('club_kpi_values')->insert(array_merge($row, ['reference_period' => '']))],
    'negativo' => [fn (array $row) => DB::table('club_kpi_values')->insert(array_merge($row, ['value' => -1]))],
]);
