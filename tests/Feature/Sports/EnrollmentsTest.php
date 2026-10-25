<?php

use App\Domain\Sports\Actions\EnrollClubs;
use App\Enums\AuditAction;
use App\Enums\CompetitionType;
use App\Models\AuditEvent;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Season;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Seasonal enrollments (M02 F-06, FL-01 step 2; D-185; D-137, D-321: admin only; D-317).
| Permissions of every route are in the route × role matrix (RouteMatrixTest).
*/

it('F-06: the page of an edition shows the enrolled clubs and the active clubs that can be enrolled', function () {
    $edition = CompetitionSeason::factory()->create();
    $enrolled = Club::factory()->create(['name' => 'AC ALFA']);
    Club::factory()->create(['name' => 'BETA FC', 'is_infront' => true]);
    Club::factory()->create(['name' => 'GAMMA', 'is_active' => false]);
    ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $enrolled->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('sports.editions.show', $edition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sports/Editions/Show')
            ->has('enrollments', 1)
            ->where('enrollments.0.clubName', 'AC ALFA')
            ->where('availableClubs', fn ($options): bool => collect($options)->pluck('label')->all() === ['BETA FC · Infront']));
});

it('F-06, D-127: enrolls several clubs, with one audit event each', function () {
    $edition = CompetitionSeason::factory()->create();
    $clubs = Club::factory()->count(3)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.enrollments.store', $edition), ['club_ids' => $clubs->pluck('id')->all()])
        ->assertSessionHas('toast.message', 'Club iscritti a '.$edition->label().': 3');

    expect(ClubEnrollment::query()->where('competition_season_id', $edition->id)->count())->toBe(3);

    $events = auditEventsOf(AuditAction::EnrollmentCreated);
    expect($events)->toHaveCount(3)
        ->and($events[0]->object_type)->toBe('competition_season')
        ->and($events[0]->object_label)->toBe($edition->label())
        ->and($events->pluck('new_values.club')->sort()->values()->all())->toBe($clubs->pluck('code')->sort()->values()->all());
});

it('D-185: a club plays one league per season; the whole enrollment is refused', function () {
    $season = Season::factory()->create();
    $serieA = CompetitionSeason::factory()->for($season)->create();
    $serieB = CompetitionSeason::factory()->for($season)->create();
    [$free, $busy] = Club::factory()->count(2)->create()->all();
    ClubEnrollment::query()->create(['competition_season_id' => $serieA->id, 'club_id' => $busy->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.enrollments.store', $serieB), ['club_ids' => [$free->id, $busy->id]])
        ->assertSessionHas('toast.message', fn (string $message): bool => str_contains($message, $busy->name.' è già iscritto a '.$serieA->competition->name));

    expect(ClubEnrollment::query()->where('competition_season_id', $serieB->id)->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('D-185: the same club may play a league and a cup in the same season, and leagues of different seasons', function () {
    $season = Season::factory()->create();
    $league = CompetitionSeason::factory()->for($season)->create();
    $cup = CompetitionSeason::factory()->for($season)->for(Competition::factory()->create(['type' => CompetitionType::Cup]))->create();
    $nextSeason = CompetitionSeason::factory()->create();
    $club = Club::factory()->create();
    $admin = User::factory()->admin()->create();

    foreach ([$league, $cup, $nextSeason] as $edition) {
        app(EnrollClubs::class)->handle($edition, [$club->id], $admin);
    }

    expect(ClubEnrollment::query()->where('club_id', $club->id)->count())->toBe(3);
});

it('F-06: a club already in the edition is refused', function () {
    $edition = CompetitionSeason::factory()->create();
    $club = Club::factory()->create();
    ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $club->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.enrollments.store', $edition), ['club_ids' => [$club->id]])
        ->assertSessionHas('toast.message', $club->name.' è già iscritto a questa competizione');
});

it('FL-01: removes a club without matches, with an audit event', function () {
    $edition = CompetitionSeason::factory()->create();
    $club = Club::factory()->create();
    $enrollment = ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $club->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('sports.enrollments.destroy', $enrollment))
        ->assertSessionHas('toast.message', $club->name.' tolto da '.$edition->label());

    expect(ClubEnrollment::query()->count())->toBe(0)
        ->and(soleAuditEvent(AuditAction::EnrollmentRemoved)->old_values)->toBe(['club' => $club->code]);
});

it('FL-01: a club with matches in the edition stays enrolled, even if they are cancelled', function () {
    $edition = CompetitionSeason::factory()->create();
    $match = FixtureMatch::factory()->forEdition($edition)->create(['status' => 'cancelled']);
    $enrollment = ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $match->away_club_id]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('sports.enrollments.destroy', $enrollment))
        ->assertSessionHas('toast.type', 'error');

    expect(ClubEnrollment::query()->count())->toBe(1);
});

it('D-317: in a closed season enrollments cannot change', function () {
    $edition = CompetitionSeason::factory()->for(Season::factory()->closed())->create();
    $club = Club::factory()->create();
    $enrollment = ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => Club::factory()->create()->id]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('sports.enrollments.store', $edition), ['club_ids' => [$club->id]])
        ->assertSessionHas('toast.message', fn (string $message): bool => str_contains($message, 'è chiusa'));
    $this->actingAs($admin)->delete(route('sports.enrollments.destroy', $enrollment))
        ->assertSessionHas('toast.message', fn (string $message): bool => str_contains($message, 'è chiusa'));

    expect(ClubEnrollment::query()->count())->toBe(1);
});

it('F-06: at least one club must be chosen', function () {
    $edition = CompetitionSeason::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('sports.enrollments.store', $edition), ['club_ids' => []])
        ->assertSessionHasErrors(['club_ids' => 'Il campo club è obbligatorio.']);
});
