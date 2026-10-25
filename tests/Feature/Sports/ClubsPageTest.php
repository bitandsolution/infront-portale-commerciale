<?php

use App\Domain\Sports\Actions\AddClubAlias;
use App\Enums\AliasSource;
use App\Enums\AuditAction;
use App\Enums\FilePurpose;
use App\Models\AuditEvent;
use App\Models\Club;
use App\Models\ClubAlias;
use App\Models\Stadium;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Files\SampleFiles;
use App\Support\Navigation\PortalPages;
use Database\Factories\DisciplineFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Clubs page (M02 F-05, F-11, RB-07, RB-08; D-137: admin only; D-188, D-253). Permissions of every
| route are in the route × role matrix (RouteMatrixTest).
*/

beforeEach(function () {
    DisciplineFactory::football();
    Storage::fake(StoredFile::DISK);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clubsForm(array $overrides = []): array
{
    return array_merge([
        'code' => 'ROVERETO',
        'name' => 'AC ROVERETO',
        'short_name' => 'Rovereto',
        'home_stadium_id' => '',
        'primary_color' => '',
        'secondary_color' => '',
        'is_infront' => true,
        'is_top_match_opponent' => false,
        'is_active' => true,
    ], $overrides);
}

it('F-05: the admin sees Infront clubs and opponents with their flags and logo', function () {
    $stadium = Stadium::factory()->create(['name' => 'Stadio Quercia']);
    Club::factory()->infront()->create(['name' => 'AC ALFA', 'home_stadium_id' => $stadium->id]);
    Club::factory()->topMatch()->create(['name' => 'BETA FC']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(PortalPages::url('sports_clubs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sports/Clubs/Index')
            ->has('clubs', 2)
            ->where('clubs.0.name', 'AC ALFA')
            ->where('clubs.0.isInfront', true)
            ->where('clubs.0.homeStadium', 'Stadio Quercia')
            ->where('clubs.0.logoUrl', null)
            ->where('clubs.1.isTopMatchOpponent', true));
});

it('F-05, D-127: creates a club with code and colors in capital letters, with an audit event', function () {
    $stadium = Stadium::factory()->create(['name' => 'Stadio Quercia']);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.clubs.store'), clubsForm([
            'code' => ' rovereto ', 'home_stadium_id' => (string) $stadium->id,
            'primary_color' => '#c8102e', 'is_top_match_opponent' => true,
        ]));

    $club = Club::query()->sole();
    $response->assertRedirect(route('sports.clubs.edit', $club))
        ->assertSessionHas('toast.message', 'Club AC ROVERETO creato. Ora puoi caricare il logo e aggiungere gli alias');

    expect($club->code)->toBe('ROVERETO')
        ->and($club->primary_color)->toBe('#C8102E')
        ->and($club->secondary_color)->toBeNull()
        ->and($club->is_infront)->toBeTrue()
        ->and($club->discipline->code)->toBe('CALCIO');

    $event = soleAuditEvent(AuditAction::ClubCreated);
    expect($event->object_label)->toBe('AC ROVERETO (ROVERETO)')
        ->and($event->new_values)->toMatchArray([
            'club_code' => 'ROVERETO', 'home_stadium' => 'Stadio Quercia', 'primary_color' => '#C8102E',
            'is_infront' => true, 'is_top_match_opponent' => true,
        ]);
});

it('F-05: validates the fields of a club', function (array $values, string $field, string $message) {
    Club::factory()->create(['code' => 'GIA_USATO']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.clubs.store'), clubsForm($values))
        ->assertSessionHasErrors([$field => $message]);

    expect(Club::query()->count())->toBe(1);
})->with([
    'code with spaces' => [['code' => 'AC ROVERETO'], 'code', 'Il formato del campo codice non è valido.'],
    'code already used' => [['code' => 'gia_usato'], 'code', 'Il valore del campo codice è già in uso.'],
    'color' => [['primary_color' => 'rosso'], 'primary_color', 'Il colore va scritto come #RRGGBB, per esempio #C8102E.'],
    'unknown stadium' => [['home_stadium_id' => '999999'], 'home_stadium_id', 'Il valore scelto per stadio abituale non esiste.'],
    'empty short name' => [['short_name' => ''], 'short_name', 'Il campo nome breve è obbligatorio.'],
]);

it('C3, C4, RB-08: changes and deactivates a club, the code stays, the audit has only the changes', function () {
    $club = Club::factory()->infront()->create(['code' => 'ROVERETO', 'name' => 'AC ROVERETO', 'short_name' => 'Rovereto']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('sports.clubs.update', $club), clubsForm(['code' => 'ALTRO', 'is_active' => false, 'is_top_match_opponent' => true]))
        ->assertSessionHas('toast.message', 'Club AC ROVERETO salvato');

    $club->refresh();
    expect($club->code)->toBe('ROVERETO')
        ->and($club->is_active)->toBeFalse()
        ->and($club->is_top_match_opponent)->toBeTrue();

    $event = soleAuditEvent(AuditAction::ClubUpdated);
    expect($event->old_values)->toBe(['is_top_match_opponent' => false, 'is_active' => true])
        ->and($event->new_values)->toBe(['is_top_match_opponent' => true, 'is_active' => false]);
});

it('M02-CA7: a club can never be deleted from the portal, only deactivated', function () {
    expect(collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'sports.clubs.') && in_array('DELETE', $route->methods(), true))
        ->map(fn ($route): string => (string) $route->getName())
        ->values()
        ->all())->toBe(['sports.clubs.aliases.destroy']);
});

it('D-218, D-230: uploads a re-encoded logo, keeps the previous file and records both in the audit', function () {
    $club = Club::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('sports.clubs.logo', $club), ['logo' => UploadedFile::fake()->createWithContent('logo.png', SampleFiles::png(400, 400))])
        ->assertSessionHas('toast.message', 'Logo di '.$club->name.' aggiornato');
    $first = $club->refresh()->logo_file_id;

    $this->actingAs($admin)
        ->post(route('sports.clubs.logo', $club), ['logo' => UploadedFile::fake()->createWithContent('logo.jpg', SampleFiles::jpeg(500, 300))]);
    $second = $club->refresh()->logo_file_id;

    expect($first)->not->toBeNull()
        ->and($second)->not->toBe($first)
        ->and(StoredFile::query()->whereKey([$first, $second])->get()->map(fn (StoredFile $file): string => $file->purpose->value)->unique()->values()->all())->toBe(['club_logo'])
        ->and(StoredFile::query()->find($first))->not->toBeNull();

    $events = auditEventsOf(AuditAction::ClubLogoChanged);
    expect($events)->toHaveCount(2)
        ->and($events[1]->old_values)->toBe(['logo_file_id' => $first])
        ->and($events[1]->new_values)->toBe(['logo_file_id' => $second]);
});

it('D-288: refuses a logo that is not a PNG or JPEG image, saving nothing', function () {
    $club = Club::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.clubs.logo', $club), ['logo' => UploadedFile::fake()->createWithContent('logo.png', SampleFiles::svg())])
        ->assertSessionHasErrors(['logo' => 'Formato non ammesso: carica un file PNG o JPG.']);

    expect($club->refresh()->logo_file_id)->toBeNull()
        ->and(StoredFile::query()->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('D-289, D-321: every active role can download a club logo; other files stay admin only', function () {
    $club = Club::factory()->create();
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.clubs.logo', $club), ['logo' => UploadedFile::fake()->createWithContent('logo.png', SampleFiles::png(400, 400))]);
    $logo = (int) $club->refresh()->logo_file_id;
    $clientLogo = storeTestFile(FilePurpose::ClientLogo);

    foreach ([User::factory()->agent()->create(), User::factory()->manager()->create()] as $user) {
        $this->actingAs($user)->get(route('files.download', $logo))->assertOk();
        $this->actingAs($user)->get(route('files.download', $clientLogo->id))->assertForbidden();
    }

    $this->actingAs(User::factory()->agent()->inactive()->create())->get(route('files.download', $logo))->assertRedirect(route('login'));
});

it('F-11: adds an alias, normalized, with an audit event on the club', function () {
    $club = Club::factory()->create(['name' => 'SS LAZIO', 'short_name' => 'Lazio']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.clubs.aliases.store', $club), ['alias' => ' S.S. Lazio 1900 '])
        ->assertSessionHas('toast.message', 'Alias «S.S. Lazio 1900» aggiunto');

    $alias = ClubAlias::query()->sole();
    expect($alias->alias)->toBe('S.S. Lazio 1900')
        ->and($alias->alias_normalized)->toBe('S S LAZIO 1900')
        ->and($alias->source)->toBe(AliasSource::Manual);

    $event = soleAuditEvent(AuditAction::ClubAliasCreated);
    expect($event->object_type)->toBe('club')
        ->and($event->object_id)->toBe($club->id)
        ->and($event->new_values)->toBe(['alias' => 'S.S. Lazio 1900', 'source' => 'manual']);
});

it('D-185: an alias already used by another club, or equal to the name of a club, is refused', function (string $alias) {
    $lazio = Club::factory()->create(['name' => 'SS LAZIO', 'short_name' => 'Lazio']);
    $roma = Club::factory()->create(['name' => 'AS ROMA', 'short_name' => 'Roma']);
    app(AddClubAlias::class)->handle($lazio, 'Lazio Roma', AliasSource::Manual, null);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.clubs.aliases.store', $roma), ['alias' => $alias])
        ->assertSessionHas('toast.type', 'error')
        ->assertSessionHas('toast.message', fn (string $message): bool => str_contains($message, 'identifica già SS LAZIO'));

    expect(ClubAlias::query()->count())->toBe(1);
})->with([
    'alias of another club' => ['lazio-roma'],
    'name of another club' => ['ss  lazio'],
    'short name of another club' => ['LAZIO'],
]);

it('F-11: an alias without letters or digits is refused', function () {
    $club = Club::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.clubs.aliases.store', $club), ['alias' => '...'])
        ->assertSessionHas('toast.message', "L'alias deve contenere almeno una lettera o una cifra");

    expect(ClubAlias::query()->count())->toBe(0);
});

it('F-11: removes an alias, keeping its text in the audit', function () {
    $club = Club::factory()->create();
    $alias = app(AddClubAlias::class)->handle($club, 'Vecchio nome', AliasSource::Manual, null);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('sports.clubs.aliases.destroy', $alias))
        ->assertSessionHas('toast.message', 'Alias «Vecchio nome» tolto');

    expect(ClubAlias::query()->count())->toBe(0)
        ->and(soleAuditEvent(AuditAction::ClubAliasRemoved)->old_values)->toBe(['alias' => 'Vecchio nome', 'source' => 'manual']);
});

it('F-05: the page of a club shows its data, logo upload and aliases', function () {
    $club = Club::factory()->create(['name' => 'SS LAZIO']);
    app(AddClubAlias::class)->handle($club, 'S.S. Lazio', AliasSource::Manual, null);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('sports.clubs.edit', $club))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sports/Clubs/Form')
            ->where('club.name', 'SS LAZIO')
            ->where('logoUrl', route('sports.clubs.logo', $club))
            ->has('aliases', 1)
            ->where('aliases.0.alias', 'S.S. Lazio')
            ->where('aliases.0.sourceLabel', 'Inserito a mano'));
});
