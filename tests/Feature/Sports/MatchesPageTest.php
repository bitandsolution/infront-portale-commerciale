<?php

use App\Domain\Sports\Services\CurrentSeason;
use App\Enums\AuditAction;
use App\Enums\MatchSource;
use App\Enums\MatchStatus;
use App\Models\AuditEvent;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Season;
use App\Models\Stadium;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Matches page (M02 F-08, §8, §9, FL-03; D-137: admin and manager; D-317, D-319). Permissions of
| every route are in the route × role matrix (RouteMatrixTest).
*/

/**
 * A 2051-52 edition with three enrolled clubs (the first with a home stadium), selected in the
 * session of the top bar.
 *
 * @return array{0: CompetitionSeason, 1: Club, 2: Club, 3: Club}
 */
function matchesEdition(bool $closed = false): array
{
    $season = Season::factory()->forYear(2051)->state(['status' => $closed ? 'closed' : 'open'])->create();
    $edition = CompetitionSeason::factory()->for($season)->create();
    $home = Club::factory()->create(['short_name' => 'Alfa', 'home_stadium_id' => Stadium::factory()->create(['name' => 'Stadio Alfa'])->id]);
    $away = Club::factory()->create(['short_name' => 'Beta']);
    $third = Club::factory()->create(['short_name' => 'Gamma']);

    foreach ([$home, $away, $third] as $club) {
        ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $club->id]);
    }

    session([CurrentSeason::SESSION_KEY => $season->id]);

    return [$edition, $home, $away, $third];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function matchesForm(CompetitionSeason $edition, Club $home, Club $away, array $overrides = []): array
{
    return array_merge([
        'competition_season_id' => $edition->id,
        'stage' => 'regular',
        'matchday' => 7,
        'match_date' => '2051-10-18',
        'kickoff_time' => '20:45',
        'home_club_id' => $home->id,
        'away_club_id' => $away->id,
        'stadium_id' => '',
    ], $overrides);
}

it('F-08: the manager inserts a match; the stadium defaults to the home stadium; audit match.created', function () {
    [$edition, $home, $away] = matchesEdition();

    $this->actingAs(User::factory()->manager()->create())
        ->post(route('sports.matches.store'), matchesForm($edition, $home, $away))
        ->assertRedirect(PortalPages::url('sports_matches').'?edizione='.$edition->id)
        ->assertSessionHas('toast.message', 'Partita Alfa – Beta, 7ª giornata, 18/10/2051 inserita');

    $match = FixtureMatch::query()->sole();
    expect($match->stadium_id)->toBe($home->home_stadium_id)
        ->and($match->kickoff())->toBe('20:45')
        ->and($match->source)->toBe(MatchSource::Manual)
        ->and($match->status)->toBe(MatchStatus::Scheduled)
        ->and($match->matchday->number)->toBe(7);

    expect(soleAuditEvent(AuditAction::MatchCreated)->new_values)->toBe([
        'stage' => 'regular', 'matchday' => 7, 'match_date' => '2051-10-18', 'kickoff_time' => '20:45',
        'home_club' => $home->code, 'away_club' => $away->code, 'stadium' => 'Stadio Alfa', 'status' => 'scheduled',
    ]);
});

it('M02 §8: refuses a date outside the season, a club not enrolled and a duplicate', function () {
    [$edition, $home, $away] = matchesEdition();
    $stranger = Club::factory()->create();
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)->post(route('sports.matches.store'), matchesForm($edition, $home, $away, ['match_date' => '2052-07-01']))
        ->assertSessionHas('toast.message', 'La data è fuori dalla stagione 2051-52');
    $this->actingAs($manager)->post(route('sports.matches.store'), matchesForm($edition, $home, $stranger))
        ->assertSessionHas('toast.message', fn (string $message): bool => str_starts_with($message, $stranger->name.' non è iscritto a'));

    $this->actingAs($manager)->post(route('sports.matches.store'), matchesForm($edition, $home, $away))
        ->assertSessionHas('toast.type', 'success');
    $this->actingAs($manager)->post(route('sports.matches.store'), matchesForm($edition, $home, $away, ['match_date' => '2051-10-19']))
        ->assertSessionHas('toast.message', 'Esiste già una partita con la stessa giornata, squadra di casa e squadra ospite');

    expect(FixtureMatch::query()->count())->toBe(1);
});

it('M02 §8: home and away must be different clubs, the matchday a whole number greater than zero', function () {
    [$edition, $home, $away] = matchesEdition();

    $this->actingAs(User::factory()->manager()->create())
        ->post(route('sports.matches.store'), matchesForm($edition, $home, $home, ['matchday' => 0]))
        ->assertSessionHasErrors([
            'away_club_id' => 'I campi squadra ospite e squadra di casa devono essere diversi.',
            'matchday' => 'Il campo giornata deve essere compreso tra 1 e 99.',
        ]);
});

it('M02 §8: a club with another match on the same day is a warning, not a block', function () {
    [$edition, $home, $away, $third] = matchesEdition();
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager)->post(route('sports.matches.store'), matchesForm($edition, $home, $away))
        ->assertSessionHas('toast.type', 'success');

    $this->actingAs($manager)
        ->post(route('sports.matches.store'), matchesForm($edition, $third, $home, ['matchday' => 8]))
        ->assertSessionHas('toast.type', 'error')
        ->assertSessionHas('toast.message', fn (string $message): bool => str_contains($message, 'inserita. Attenzione: '.$home->name.' gioca già un\'altra partita il 18/10/2051'));

    expect(FixtureMatch::query()->count())->toBe(2);
});

it('FL-03: a change of date (rinvio) updates the same match, with only the changes in the audit', function () {
    [$edition, $home, $away] = matchesEdition();
    $match = FixtureMatch::factory()->forEdition($edition, 7)->create([
        'home_club_id' => $home->id, 'away_club_id' => $away->id, 'match_date' => '2051-10-18', 'stadium_id' => $home->home_stadium_id,
    ]);

    $this->actingAs(User::factory()->manager()->create())
        ->put(route('sports.matches.update', $match), matchesForm($edition, $home, $away, ['match_date' => '2051-11-02', 'kickoff_time' => '']))
        ->assertSessionHas('toast.message', 'Partita Alfa – Beta, 7ª giornata, 02/11/2051 salvata');

    expect(FixtureMatch::query()->count())->toBe(1)
        ->and($match->refresh()->match_date->toDateString())->toBe('2051-11-02');

    $event = soleAuditEvent(AuditAction::MatchUpdated);
    expect($event->old_values)->toBe(['match_date' => '2051-10-18'])
        ->and($event->new_values)->toBe(['match_date' => '2051-11-02']);
});

it('D-319: a match is cancelled and restored, never deleted, with audit events', function () {
    [$edition, $home, $away] = matchesEdition();
    $match = FixtureMatch::factory()->forEdition($edition)->create(['home_club_id' => $home->id, 'away_club_id' => $away->id, 'match_date' => '2051-10-18']);
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)->post(route('sports.matches.cancel', $match))
        ->assertSessionHas('toast.message', 'Partita Alfa – Beta, 1ª giornata, 18/10/2051 annullata');
    expect($match->refresh()->status)->toBe(MatchStatus::Cancelled);

    $this->actingAs($manager)->post(route('sports.matches.restore', $match));
    expect($match->refresh()->status)->toBe(MatchStatus::Scheduled)
        ->and(soleAuditEvent(AuditAction::MatchCancelled)->new_values)->toBe(['status' => 'cancelled'])
        ->and(soleAuditEvent(AuditAction::MatchRestored)->new_values)->toBe(['status' => 'scheduled']);
});

it('D-317: in a closed season matches cannot be inserted, changed or cancelled', function () {
    [$edition, $home, $away] = matchesEdition(closed: true);
    $match = FixtureMatch::factory()->forEdition($edition)->create(['home_club_id' => $home->id, 'away_club_id' => $away->id, 'match_date' => '2051-10-18']);
    $manager = User::factory()->manager()->create();
    $closed = fn (string $message): bool => str_contains($message, 'è chiusa');

    $this->actingAs($manager)->post(route('sports.matches.store'), matchesForm($edition, $away, $home))->assertSessionHas('toast.message', $closed);
    $this->actingAs($manager)->put(route('sports.matches.update', $match), matchesForm($edition, $home, $away, ['match_date' => '2051-10-20']))->assertSessionHas('toast.message', $closed);
    $this->actingAs($manager)->post(route('sports.matches.cancel', $match))->assertSessionHas('toast.message', $closed);

    expect(FixtureMatch::query()->count())->toBe(1)
        ->and($match->refresh()->status)->toBe(MatchStatus::Scheduled)
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('F-08: the list shows the matches of one competition, filtered by club and matchday', function () {
    [$edition, $home, $away, $third] = matchesEdition();
    FixtureMatch::factory()->forEdition($edition, 1)->create(['home_club_id' => $home->id, 'away_club_id' => $away->id, 'match_date' => '2051-08-24']);
    FixtureMatch::factory()->forEdition($edition, 2)->create(['home_club_id' => $third->id, 'away_club_id' => $home->id, 'match_date' => '2051-08-31']);
    FixtureMatch::factory()->forEdition($edition, 2)->create(['home_club_id' => $away->id, 'away_club_id' => $third->id, 'match_date' => '2051-08-31', 'kickoff_time' => '18:00']);
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)
        ->get(PortalPages::url('sports_matches'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sports/Matches/Index')
            ->where('filters.edition', (string) $edition->id)
            ->has('clubs', 3)
            ->has('matches', 3)
            ->where('matches.0.matchday', 1)
            ->where('matches.0.homeClub', 'Alfa')
            ->where('matches.0.date', '24/08/2051')
            ->where('matches.0.kickoff', null));

    $this->actingAs($manager)
        ->get(PortalPages::url('sports_matches').'?edizione='.$edition->id.'&club='.$third->id.'&giornata=2')
        ->assertInertia(fn (Assert $page) => $page->has('matches', 2)->where('filters.club', (string) $third->id));

    $this->actingAs($manager)
        ->get(PortalPages::url('sports_matches').'?edizione='.$edition->id.'&club='.$home->id)
        ->assertInertia(fn (Assert $page) => $page->has('matches', 2));
});

it('F-08: the form offers only the clubs enrolled in each competition of the season', function () {
    [$edition, $home] = matchesEdition();

    $this->actingAs(User::factory()->manager()->create())
        ->get(route('sports.matches.create', ['edizione' => $edition->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sports/Matches/Form')
            ->where('editionId', (string) $edition->id)
            ->has('clubsByEdition.'.$edition->id, 3)
            ->where('clubsByEdition.'.$edition->id.'.0.label', 'Alfa'));
});
