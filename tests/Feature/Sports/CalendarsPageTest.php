<?php

use App\Domain\Sports\Services\CurrentSeason;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Season;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Calendars page (M02 F-08; D-137: admin and manager; D-317 season of the top bar).
*/

it('F-08: shows the competitions of the season with clubs, matches and state of the calendar', function () {
    $season = Season::factory()->forYear(2051)->create();
    $edition = CompetitionSeason::factory()->for($season)->create(['standard_home_matches' => 17]);
    $club = Club::factory()->create();
    ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $club->id]);
    FixtureMatch::factory()->forEdition($edition)->count(2)->create();
    FixtureMatch::factory()->forEdition($edition, 2)->create(['status' => 'cancelled']);

    $this->actingAs(User::factory()->manager()->create())
        ->withSession([CurrentSeason::SESSION_KEY => $season->id])
        ->get(PortalPages::url('sports_calendars'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sports/Calendars/Index')
            ->where('season.code', '2051-52')
            ->has('editions', 1)
            ->where('editions.0.enrollments', 1)
            ->where('editions.0.scheduled', 2)
            ->where('editions.0.cancelled', 1)
            ->where('editions.0.standardHomeMatches', 17)
            ->where('editions.0.calendarLoadedAt', null)
            ->where('editions.0.matchesUrl', PortalPages::url('sports_matches').'?edizione='.$edition->id)
            ->where('editions.0.enrollmentsUrl', null));
});

it('D-137: only the admin gets the link to the enrollments', function () {
    $season = Season::factory()->forYear(2051)->create();
    $edition = CompetitionSeason::factory()->for($season)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->withSession([CurrentSeason::SESSION_KEY => $season->id])
        ->get(PortalPages::url('sports_calendars'))
        ->assertInertia(fn (Assert $page) => $page->where('editions.0.enrollmentsUrl', route('sports.editions.show', $edition)));
});

it('D-317: without seasons the page says so', function () {
    $this->actingAs(User::factory()->manager()->create())
        ->get(PortalPages::url('sports_calendars'))
        ->assertInertia(fn (Assert $page) => $page->where('season', null)->where('editions', []));
});
