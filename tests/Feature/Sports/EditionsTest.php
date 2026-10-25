<?php

use App\Domain\Sports\Actions\AddCompetitionToSeason;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Season;
use App\Models\User;

/*
| Competitions of a season with their standard number of home matches (D-034, D-185, FL-01 step 3;
| D-137, D-321: admin only; D-317: read-only in a closed season).
*/

it('D-034: adds a competition to a season with its standard number, with an audit event', function () {
    $admin = User::factory()->admin()->create();
    $season = Season::factory()->forYear(2027)->create();
    $competition = Competition::factory()->create(['name' => 'Serie Prova', 'code' => 'SERIE_PROVA']);

    $this->actingAs($admin)
        ->post(route('sports.editions.store', $season), ['competition_id' => $competition->id, 'standard_home_matches' => 19])
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'Serie Prova 2027-28 aggiunta alla stagione.');

    $edition = CompetitionSeason::query()->sole();
    expect($edition->season_id)->toBe($season->id)
        ->and($edition->standard_home_matches)->toBe(19)
        ->and($edition->calendar_loaded_at)->toBeNull();

    $event = soleAuditEvent(AuditAction::EditionCreated);
    expect($event->area)->toBe('sports')
        ->and($event->object_type)->toBe('competition_season')
        ->and($event->object_label)->toBe('Serie Prova 2027-28')
        ->and($event->new_values)->toBe(['competition' => 'SERIE_PROVA', 'season' => '2027-28', 'standard_home_matches' => 19]);
});

it('M02 §8: the standard number must be a whole number greater than zero', function (mixed $value, string $message) {
    $season = Season::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.editions.store', $season), ['competition_id' => Competition::factory()->create()->id, 'standard_home_matches' => $value])
        ->assertSessionHasErrors(['standard_home_matches' => $message]);

    expect(CompetitionSeason::query()->count())->toBe(0);
})->with([
    'zero' => [0, 'Il campo numero standard di partite in casa deve essere compreso tra 1 e 99.'],
    'empty' => ['', 'Il campo numero standard di partite in casa è obbligatorio.'],
    'decimal' => ['18,5', 'Il campo numero standard di partite in casa deve essere un numero intero.'],
]);

it('D-185: the same competition cannot be added twice to a season', function () {
    $edition = CompetitionSeason::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.editions.store', $edition->season), ['competition_id' => $edition->competition_id, 'standard_home_matches' => 17])
        ->assertSessionHas('toast.type', 'error');

    expect(CompetitionSeason::query()->count())->toBe(1)
        ->and($edition->refresh()->standard_home_matches)->toBe(19);
});

it('D-034: changes the standard number with the value before and after in the audit', function () {
    $edition = CompetitionSeason::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('sports.editions.update', $edition), ['standard_home_matches' => 17])
        ->assertSessionHas('toast.message', 'Numero standard di '.$edition->label().' salvato.');

    expect($edition->refresh()->standard_home_matches)->toBe(17);

    $event = soleAuditEvent(AuditAction::EditionUpdated);
    expect($event->old_values)->toBe(['standard_home_matches' => 19])
        ->and($event->new_values)->toBe(['standard_home_matches' => 17]);
});

it('removes an empty edition added by mistake, recording what it was', function () {
    $edition = CompetitionSeason::factory()->create();
    $label = $edition->label();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('sports.editions.destroy', $edition))
        ->assertSessionHas('toast.message', $label.' tolta dalla stagione.');

    expect(CompetitionSeason::query()->count())->toBe(0);

    $event = soleAuditEvent(AuditAction::EditionRemoved);
    expect($event->object_label)->toBe($label)
        ->and($event->old_values)->toMatchArray(['standard_home_matches' => 19])
        ->and($event->new_values)->toBeNull();
});

it('D-317: in a closed season competitions cannot be added, changed or removed', function () {
    $season = Season::factory()->closed()->create();
    $edition = CompetitionSeason::factory()->for($season)->create();
    $admin = User::factory()->admin()->create();
    $message = 'La stagione '.$season->code.' è chiusa: le sue anagrafiche si consultano soltanto. Per modificarle riapri la stagione.';

    $this->actingAs($admin)
        ->post(route('sports.editions.store', $season), ['competition_id' => Competition::factory()->create()->id, 'standard_home_matches' => 19])
        ->assertSessionHas('toast.message', $message);
    $this->actingAs($admin)
        ->put(route('sports.editions.update', $edition), ['standard_home_matches' => 10])
        ->assertSessionHas('toast.message', $message);
    $this->actingAs($admin)
        ->delete(route('sports.editions.destroy', $edition))
        ->assertSessionHas('toast.message', $message);

    expect(CompetitionSeason::query()->count())->toBe(1)
        ->and($edition->refresh()->standard_home_matches)->toBe(19)
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('D-317: the page of a closed season shows its competitions as read-only', function () {
    $season = Season::factory()->closed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('sports.seasons.edit', $season))
        ->assertInertia(fn ($page) => $page->where('season.readOnly', true));
});

it('D-284: an audit event that cannot be written cancels the new edition (fail-closed)', function () {
    $season = Season::factory()->create();
    AuditEvent::creating(fn () => throw new RuntimeException('audit non scrivibile'));

    expect(fn () => app(AddCompetitionToSeason::class)->handle($season, Competition::factory()->create(), 19, User::factory()->admin()->create()))
        ->toThrow(RuntimeException::class);

    expect(CompetitionSeason::query()->count())->toBe(0);
});
