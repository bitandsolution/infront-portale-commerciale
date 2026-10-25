<?php

use App\Domain\Sports\Services\CurrentSeason;
use App\Models\AuditEvent;
use App\Models\Season;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Season selector of the top bar (D-317): the season containing today, otherwise the next one,
| otherwise the last one; the choice of the user stays in his session.
*/

it('D-317: the default is the season containing today', function () {
    Season::factory()->forYear(2026)->create();
    $current = Season::factory()->forYear(2027)->create();
    Season::factory()->forYear(2028)->create();

    expect(CurrentSeason::default(Carbon::parse('2027-10-10'))?->is($current))->toBeTrue();
});

it('D-317: between two seasons, or before the first one, the default is the next season', function () {
    Season::factory()->forYear(2026)->create(['ends_on' => '2027-05-31']);
    $next = Season::factory()->forYear(2027)->create();

    // Go-live (24/5/2027) before 2027-28, after the end of 2026-27
    expect(CurrentSeason::default(Carbon::parse('2027-06-15'))?->is($next))->toBeTrue()
        ->and(CurrentSeason::default(Carbon::parse('2020-01-01'))?->code)->toBe('2026-27');
});

it('D-317: after the last season the default is the last one; without seasons there is none', function () {
    expect(CurrentSeason::default(Carbon::parse('2027-10-10')))->toBeNull();

    Season::factory()->forYear(2026)->create();
    $last = Season::factory()->forYear(2027)->create();

    expect(CurrentSeason::default(Carbon::parse('2040-01-01'))?->is($last))->toBeTrue();
});

it('D-317: the top bar shows the seasons, the most recent first, with the default selected', function () {
    $this->travelTo(Carbon::parse('2027-10-10 12:00', 'Europe/Rome'));
    $older = Season::factory()->forYear(2026)->create();
    $current = Season::factory()->forYear(2027)->create();

    $this->actingAs(User::factory()->agent()->create())
        ->get(PortalPages::url('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('layout.season.enabled', true)
            ->where('layout.season.label', '2027-28')
            ->where('layout.season.current', $current->id)
            ->where('layout.season.options', [
                ['value' => (string) $current->id, 'label' => '2027-28'],
                ['value' => (string) $older->id, 'label' => '2026-27'],
            ])
            ->where('layout.season.selectUrl', route('season.select')));
});

it('D-317: without seasons the selector is disabled', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(PortalPages::url('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('layout.season.enabled', false)
            ->where('layout.season.label', 'Nessuna stagione')
            ->where('layout.season.current', null));
});

it('D-317: every role chooses a season, kept in the session, without audit events', function () {
    $this->travelTo(Carbon::parse('2027-10-10 12:00', 'Europe/Rome'));
    $older = Season::factory()->forYear(2026)->create();
    Season::factory()->forYear(2027)->create();
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->from(PortalPages::url('dashboard'))
        ->post(route('season.select'), ['season_id' => $older->id])
        ->assertRedirect(PortalPages::url('dashboard'))
        ->assertSessionHas(CurrentSeason::SESSION_KEY, $older->id);

    $this->actingAs($agent)
        ->get(PortalPages::url('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('layout.season.current', $older->id)
            ->where('layout.season.label', '2026-27'));

    expect(AuditEvent::query()->count())->toBe(0);
});

it('D-317: a season that no longer exists in the session falls back to the default', function () {
    $this->travelTo(Carbon::parse('2027-10-10 12:00', 'Europe/Rome'));
    $current = Season::factory()->forYear(2027)->create();

    $this->actingAs(User::factory()->agent()->create())
        ->withSession([CurrentSeason::SESSION_KEY => 999999])
        ->get(PortalPages::url('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('layout.season.current', $current->id));
});

it('D-317: an unknown season is refused', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->post(route('season.select'), ['season_id' => 999999])
        ->assertSessionHasErrors(['season_id' => 'Il valore scelto per stagione non esiste.']);
});
