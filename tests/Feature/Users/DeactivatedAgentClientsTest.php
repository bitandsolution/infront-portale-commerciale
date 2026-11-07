<?php

use App\Domain\Clients\Actions\MergeClients;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Clients of a deactivated agent (M01-CA4; D-082, D-282, D-360; slice F3-04): the dialog
| "Disattiva utente" shows how many clients the agent has and may hand them all to an active agent,
| in the same transaction as the deactivation; the reassignment is optional.
*/

beforeEach(function () {
    Mail::fake();
    Storage::fake(StoredFile::DISK);
});

function leavingAgent(): User
{
    return User::factory()->agent()->create(['first_name' => 'Luca', 'last_name' => 'Uscente']);
}

function receivingAgent(): User
{
    return User::factory()->agent()->create(['first_name' => 'Bruno', 'last_name' => 'Beta']);
}

it('M01-CA4: deactivating an agent, the admin hands all his clients to an active agent in the same step', function () {
    $admin = User::factory()->admin()->create();
    $leaving = leavingAgent();
    $receiving = receivingAgent();
    $active = Client::factory()->ownedBy($leaving)->named('Alfa Spa')->create();
    $inactive = Client::factory()->ownedBy($leaving)->named('Beta Spa')->create(['is_active' => false]);
    $other = Client::factory()->named('Gamma Spa')->create();

    $this->actingAs($admin)
        ->from(route('users.show', $leaving))
        ->post(route('users.deactivate', $leaving), ['clients_owner_id' => (string) $receiving->id])
        ->assertRedirect(route('users.show', $leaving))
        ->assertSessionHas('toast.message', 'Luca Uscente è stato disattivato; clienti assegnati a Bruno Beta: 2');

    expect($leaving->refresh()->is_active)->toBeFalse()
        ->and($active->refresh()->owner_id)->toBe($receiving->id)
        ->and($inactive->refresh()->owner_id)->toBe($receiving->id)
        ->and($inactive->is_active)->toBeFalse()
        ->and($other->refresh()->owner_id)->not->toBe($receiving->id);

    expect(soleAuditEvent(AuditAction::UserDeactivated)->object_id)->toBe($leaving->id);
    $reassigned = auditEventsOf(AuditAction::ClientReassigned);
    expect($reassigned->pluck('object_id')->sort()->values()->all())->toBe([$active->id, $inactive->id])
        ->and($reassigned->every(fn (AuditEvent $event): bool => $event->user_id === $admin->id
            && $event->new_values === ['owner_id' => $receiving->id, 'owner_name' => 'Bruno Beta']))->toBeTrue();

    // M05-CA6 through the deactivation: the receiving agent sees and uses the clients.
    $this->actingAs($receiving)
        ->get(PortalPages::url('clients_mine'))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 2));
});

it('M01-CA4, D-360: without a receiving agent the clients stay with the deactivated agent and remain reassignable', function () {
    $admin = User::factory()->admin()->create();
    $leaving = leavingAgent();
    $client = Client::factory()->ownedBy($leaving)->named('Alfa Spa')->create();

    $this->actingAs($admin)
        ->post(route('users.deactivate', $leaving))
        ->assertSessionHas('toast.message', 'Luca Uscente è stato disattivato');

    expect($leaving->refresh()->is_active)->toBeFalse()
        ->and($client->refresh()->owner_id)->toBe($leaving->id)
        ->and(AuditEvent::query()->where('action', AuditAction::ClientReassigned->value)->exists())->toBeFalse();

    // The page of the deactivated agent counts them and links to the filtered client list.
    $this->actingAs($admin)
        ->get(route('users.show', $leaving))
        ->assertInertia(fn (Assert $page) => $page
            ->where('clients.count', 1)
            ->where('clients.listUrl', PortalPages::url('clients').'?agenti=disattivati'));

    // The client list shows the client as belonging to a deactivated agent.
    $this->actingAs($admin)
        ->get(PortalPages::url('clients').'?agenti=disattivati')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filter.deactivatedOwners', true)
            ->where('clients.0.companyName', 'Alfa Spa')
            ->where('clients.0.ownerActive', false));

    // Later the admin reassigns it from the page of the client (F3-03).
    $receiving = receivingAgent();
    $this->actingAs($admin)->post(route('clients.reassign', $client), ['owner_id' => (string) $receiving->id]);
    expect($client->refresh()->owner_id)->toBe($receiving->id);
});

it('D-368: the merged clients of the agent are not moved', function () {
    $admin = User::factory()->admin()->create();
    $leaving = leavingAgent();
    $receiving = receivingAgent();
    $merged = Client::factory()->ownedBy($leaving)->named('Alfa Italia')->create();
    app(MergeClients::class)->handle($merged, Client::factory()->named('Alfa Spa')->create(), $admin);

    $this->actingAs($admin)->post(route('users.deactivate', $leaving), ['clients_owner_id' => (string) $receiving->id])
        ->assertSessionHas('toast.message', 'Luca Uscente è stato disattivato; clienti assegnati a Bruno Beta: 0');

    expect($merged->refresh()->owner_id)->toBe($leaving->id);
});

it('D-360: the clients go only to another active agent; otherwise nothing changes', function (string $case) {
    $admin = User::factory()->admin()->create();
    $leaving = leavingAgent();
    $client = Client::factory()->ownedBy($leaving)->create();
    $target = match ($case) {
        'the agent himself' => $leaving,
        'deactivated agent' => User::factory()->agent()->inactive()->create(),
        'manager' => User::factory()->manager()->create(),
    };

    $this->actingAs($admin)
        ->post(route('users.deactivate', $leaving), ['clients_owner_id' => (string) $target->id])
        ->assertSessionHas('toast.type', 'error')
        ->assertSessionHas('toast.message', 'I clienti si riassegnano solo a un altro agente attivo');

    expect($leaving->refresh()->is_active)->toBeTrue()
        ->and($client->refresh()->owner_id)->toBe($leaving->id)
        ->and(AuditEvent::query()->whereIn('action', [AuditAction::UserDeactivated->value, AuditAction::ClientReassigned->value])->exists())->toBeFalse();
})->with(['the agent himself', 'deactivated agent', 'manager']);

it('D-360: a receiving agent makes sense only when an agent is deactivated', function () {
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->manager()->create();

    $this->actingAs($admin)
        ->post(route('users.deactivate', $manager), ['clients_owner_id' => (string) receivingAgent()->id])
        ->assertSessionHas('toast.message', 'I clienti si riassegnano solo a un altro agente attivo');

    expect($manager->refresh()->is_active)->toBeTrue();
});

it('M01-CA4: the page of an agent lists his clients and the active agents who can receive them', function () {
    $admin = User::factory()->admin()->create();
    $leaving = leavingAgent();
    $receiving = receivingAgent();
    $gone = User::factory()->agent()->inactive()->create();
    Client::factory()->ownedBy($leaving)->count(2)->create();
    $merged = Client::factory()->ownedBy($leaving)->create();
    app(MergeClients::class)->handle($merged, Client::factory()->create(), $admin);

    $this->actingAs($admin)
        ->get(route('users.show', $leaving))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('clients.count', 2)
            ->where('clients.owners', fn ($owners): bool => collect($owners)->pluck('value')->contains((string) $receiving->id)
                && ! collect($owners)->pluck('value')->contains((string) $leaving->id)
                && ! collect($owners)->pluck('value')->contains((string) $gone->id)));

    $this->actingAs($admin)
        ->get(route('users.show', User::factory()->manager()->create()))
        ->assertInertia(fn (Assert $page) => $page->where('clients', null));
});
