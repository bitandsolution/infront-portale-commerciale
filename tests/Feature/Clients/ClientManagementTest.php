<?php

use App\Domain\Clients\Actions\MergeClients;
use App\Domain\Clients\Actions\ReassignClient;
use App\Domain\Clients\Exceptions\ClientsActionNotAllowed;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Files\SampleFiles;
use App\Support\Navigation\PortalPages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Reassignment, deactivation and merge of clients (M05 F-07, F-08, F-09, RB-06; D-082, D-083, D-359,
| D-360; slice F3-03). Permissions of every route are also in the route × role matrix
| (RouteMatrixTest).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
});

function managementAdmin(): User
{
    return User::factory()->admin()->create(['first_name' => 'Ada', 'last_name' => 'Admin']);
}

function managementAgent(string $first, string $last): User
{
    return User::factory()->agent()->create(['first_name' => $first, 'last_name' => $last]);
}

// ---------------------------------------------------------------- Reassignment (F-07, M05-CA6)

it('M05-CA6: after the reassignment from A to B, B sees and changes the client and A no longer sees it', function () {
    $agentA = managementAgent('Anna', 'Alfa');
    $agentB = managementAgent('Bruno', 'Beta');
    $client = Client::factory()->ownedBy($agentA)->named('Adecco Spa')->create();

    $this->actingAs(managementAdmin())
        ->from(route('clients.edit', $client))
        ->post(route('clients.reassign', $client), ['owner_id' => (string) $agentB->id])
        ->assertRedirect(route('clients.edit', $client))
        ->assertSessionHas('toast.message', 'Cliente Adecco Spa assegnato a Bruno Beta');

    expect($client->refresh()->owner_id)->toBe($agentB->id)
        ->and($client->created_by)->toBe($agentA->id);

    $this->actingAs($agentB)
        ->get(PortalPages::url('clients_mine'))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 1)->where('clients.0.companyName', 'Adecco Spa'));
    $this->actingAs($agentB)->get(route('clients.edit', $client))
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', true));

    $this->actingAs($agentA)
        ->get(PortalPages::url('clients_mine'))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 0));
    $this->actingAs($agentA)->get(route('clients.edit', $client))->assertForbidden();
});

it('M05-CA6, D-082: the reassignment writes client.reassigned with the owner before and after', function () {
    $agentA = managementAgent('Anna', 'Alfa');
    $agentB = managementAgent('Bruno', 'Beta');
    $client = Client::factory()->ownedBy($agentA)->named('Adecco Spa')->create();
    $admin = managementAdmin();

    $this->actingAs($admin)->post(route('clients.reassign', $client), ['owner_id' => (string) $agentB->id]);

    $event = soleAuditEvent(AuditAction::ClientReassigned);
    expect($event->user_id)->toBe($admin->id)
        ->and($event->object_type)->toBe('client')
        ->and($event->object_id)->toBe($client->id)
        ->and($event->old_values)->toBe(['owner_id' => $agentA->id, 'owner_name' => 'Anna Alfa'])
        ->and($event->new_values)->toBe(['owner_id' => $agentB->id, 'owner_name' => 'Bruno Beta']);
});

it('D-358: the client is reassigned only to an active agent', function (string $who) {
    $client = Client::factory()->create();
    $target = match ($who) {
        'deactivated agent' => User::factory()->agent()->inactive()->create(),
        'manager' => User::factory()->manager()->create(),
        'admin' => User::factory()->admin()->create(),
    };

    $this->actingAs(managementAdmin())
        ->post(route('clients.reassign', $client), ['owner_id' => (string) $target->id])
        ->assertSessionHasErrors('owner_id');

    expect($client->refresh()->owner_id)->not->toBe($target->id)
        ->and(AuditEvent::query()->where('action', AuditAction::ClientReassigned->value)->exists())->toBeFalse();
})->with(['deactivated agent', 'manager', 'admin']);

it('D-358: the Action refuses an owner who is not an active agent', function () {
    $client = Client::factory()->create();

    app(ReassignClient::class)->handle($client, User::factory()->agent()->inactive()->create(), managementAdmin());
})->throws(ClientsActionNotAllowed::class, 'Il cliente deve appartenere a un agente attivo');

it('D-082: reassigning a client to its current owner is refused with a toast', function () {
    $owner = managementAgent('Anna', 'Alfa');
    $client = Client::factory()->ownedBy($owner)->named('Adecco Spa')->create();

    $this->actingAs(managementAdmin())
        ->post(route('clients.reassign', $client), ['owner_id' => (string) $owner->id])
        ->assertSessionHas('toast.type', 'error')
        ->assertSessionHas('toast.message', 'Il cliente Adecco Spa è già di Anna Alfa');
});

it('M05 F-07: a deactivated client can be reassigned, its status does not change', function () {
    $client = Client::factory()->create(['is_active' => false]);
    $agent = User::factory()->agent()->create();

    $this->actingAs(managementAdmin())->post(route('clients.reassign', $client), ['owner_id' => (string) $agent->id]);

    expect($client->refresh()->owner_id)->toBe($agent->id)
        ->and($client->is_active)->toBeFalse();
});

// ---------------------------------------------------------------- Deactivation (F-09, RB-06, M05-CA7)

it('M05-CA7 (F3): a client has no deletion route, only deactivation and reactivation', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'clienti/{client}'));

    expect($routes->flatMap(fn ($route): array => $route->methods())->unique()->values()->all())->not->toContain('DELETE')
        ->and($routes->map(fn ($route): ?string => $route->getName())->values()->all())
        ->toContain('clients.deactivate', 'clients.reactivate');
});

it('M05-CA7 (F3), RB-06: the admin deactivates and reactivates a client, with an audit event each', function () {
    $owner = managementAgent('Anna', 'Alfa');
    $client = Client::factory()->ownedBy($owner)->named('Adecco Spa')->create();
    $admin = managementAdmin();

    $this->actingAs($admin)
        ->post(route('clients.deactivate', $client))
        ->assertSessionHas('toast.message', 'Cliente Adecco Spa disattivato');
    expect($client->refresh()->is_active)->toBeFalse();

    $deactivated = soleAuditEvent(AuditAction::ClientDeactivated);
    expect($deactivated->old_values)->toBe(['is_active' => true])
        ->and($deactivated->new_values)->toBe(['is_active' => false]);

    // The owner still sees his deactivated client, marked as such.
    $this->actingAs($owner)
        ->get(PortalPages::url('clients_mine'))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 1)->where('clients.0.status', 'inactive'));

    $this->actingAs($admin)
        ->post(route('clients.reactivate', $client))
        ->assertSessionHas('toast.message', 'Cliente Adecco Spa riattivato');
    expect($client->refresh()->is_active)->toBeTrue();

    $reactivated = soleAuditEvent(AuditAction::ClientReactivated);
    expect($reactivated->old_values)->toBe(['is_active' => false])
        ->and($reactivated->new_values)->toBe(['is_active' => true]);
});

it('RB-06: deactivating a deactivated client or reactivating an active one is refused', function () {
    $active = Client::factory()->named('Alfa Spa')->create();
    $inactive = Client::factory()->named('Beta Spa')->create(['is_active' => false]);
    $admin = managementAdmin();

    $this->actingAs($admin)->post(route('clients.reactivate', $active))
        ->assertSessionHas('toast.message', 'Il cliente Alfa Spa è già attivo');
    $this->actingAs($admin)->post(route('clients.deactivate', $inactive))
        ->assertSessionHas('toast.message', 'Il cliente Beta Spa è già non attivo');

    expect(AuditEvent::query()->where('object_type', 'client')->exists())->toBeFalse();
});

it('RB-06: a deactivated client still counts in the duplicate check', function () {
    $client = Client::factory()->named('Adecco Spa')->create(['is_active' => false]);
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->post(route('clients.store'), [
            'company_name' => 'ADECCO',
            'vat_number' => '',
            'email' => 'info@adecco.example',
            'contact_name' => '',
            'commodity_sector_id' => (string) $client->commodity_sector_id,
            'sector_free_text' => '',
            'logo' => UploadedFile::fake()->createWithContent('logo.png', SampleFiles::png(400, 200)),
        ])
        ->assertSessionHasErrors('duplicate');
});

// ---------------------------------------------------------------- Merge (F-08, D-359)

it('D-359: the duplicate becomes not active and points to the client that stays, which keeps its data and owner', function () {
    $agentA = managementAgent('Anna', 'Alfa');
    $agentB = managementAgent('Bruno', 'Beta');
    $duplicate = Client::factory()->ownedBy($agentA)->named('Adecco Italia', '01234567897')->create();
    $keep = Client::factory()->ownedBy($agentB)->named('Adecco Spa')->create(['email' => 'info@adecco.example']);
    $admin = managementAdmin();

    $this->actingAs($admin)
        ->post(route('clients.merge', $duplicate), ['keep_id' => (string) $keep->id])
        ->assertRedirect(route('clients.edit', $keep))
        ->assertSessionHas('toast.message', 'Cliente Adecco Italia unito a Adecco Spa');

    $duplicate->refresh();
    $kept = $keep->fresh();
    expect($duplicate->is_active)->toBeFalse()
        ->and($duplicate->merged_into_id)->toBe($keep->id)
        ->and($duplicate->owner_id)->toBe($agentA->id)
        ->and($kept?->only(['company_name', 'vat_number', 'email', 'owner_id', 'is_active', 'merged_into_id']))
        ->toBe(['company_name' => 'Adecco Spa', 'vat_number' => null, 'email' => 'info@adecco.example', 'owner_id' => $agentB->id, 'is_active' => true, 'merged_into_id' => null]);

    $event = soleAuditEvent(AuditAction::ClientMerged);
    expect($event->object_id)->toBe($duplicate->id)
        ->and($event->user_id)->toBe($admin->id)
        ->and($event->old_values)->toBe(['is_active' => true, 'merged_into_id' => null, 'merged_into_name' => null])
        ->and($event->new_values)->toBe(['is_active' => false, 'merged_into_id' => $keep->id, 'merged_into_name' => 'Adecco Spa']);
});

it('D-359: a merged client is read-only for everyone and cannot be reactivated, reassigned or merged again', function () {
    $owner = User::factory()->agent()->create();
    $keep = Client::factory()->named('Adecco Spa')->create();
    $duplicate = Client::factory()->ownedBy($owner)->named('Adecco Italia')->create();
    $admin = managementAdmin();
    app(MergeClients::class)->handle($duplicate, $keep, $admin);
    $other = Client::factory()->create();

    foreach ([$admin, $owner] as $user) {
        $this->actingAs($user)->put(route('clients.update', $duplicate), ['company_name' => 'Nuovo nome'])->assertForbidden();
    }

    $this->actingAs($admin)->post(route('clients.reactivate', $duplicate))
        ->assertSessionHas('toast.message', 'Il cliente Adecco Italia è stato unito a un altro cliente: non si modifica più');
    $this->actingAs($admin)->post(route('clients.reassign', $duplicate), ['owner_id' => (string) User::factory()->agent()->create()->id])
        ->assertSessionHas('toast.type', 'error');
    $this->actingAs($admin)->post(route('clients.merge', $duplicate), ['keep_id' => (string) $other->id])
        ->assertSessionHas('toast.type', 'error');

    expect($duplicate->refresh()->only(['is_active', 'merged_into_id', 'owner_id', 'company_name']))
        ->toBe(['is_active' => false, 'merged_into_id' => $keep->id, 'owner_id' => $owner->id, 'company_name' => 'Adecco Italia'])
        ->and(AuditEvent::query()->where('action', AuditAction::ClientMerged->value)->count())->toBe(1);
});

it('D-359: the client that stays must be another client, active and not merged', function (string $case, string $message) {
    $duplicate = Client::factory()->named('Adecco Italia')->create();
    $keep = match ($case) {
        'itself' => $duplicate,
        'inactive' => Client::factory()->named('Adecco Spa')->create(['is_active' => false]),
        'merged' => tap(Client::factory()->named('Adecco Spa')->create(), fn (Client $client) => app(MergeClients::class)
            ->handle($client, Client::factory()->create(), managementAdmin())),
    };

    $this->actingAs(managementAdmin())
        ->post(route('clients.merge', $duplicate), ['keep_id' => (string) $keep->id])
        ->assertSessionHas('toast.message', $message);

    expect($duplicate->refresh()->merged_into_id)->toBeNull()
        ->and($duplicate->is_active)->toBeTrue();
})->with([
    'itself' => ['itself', 'Scegli un cliente diverso da quello da unire'],
    'inactive' => ['inactive', 'Il cliente Adecco Spa non è attivo: il cliente che resta deve essere attivo'],
    'merged' => ['merged', 'Il cliente Adecco Spa non è attivo: il cliente che resta deve essere attivo'],
]);

it('D-359: the clients merged into the duplicate move to the client that stays', function () {
    $first = Client::factory()->named('Adecco Srl')->create();
    $duplicate = Client::factory()->named('Adecco Italia')->create();
    $keep = Client::factory()->named('Adecco Spa')->create();
    $admin = managementAdmin();
    app(MergeClients::class)->handle($first, $duplicate, $admin);

    app(MergeClients::class)->handle($duplicate, $keep, $admin);

    expect($first->refresh()->merged_into_id)->toBe($keep->id)
        ->and($duplicate->refresh()->merged_into_id)->toBe($keep->id);

    $moved = AuditEvent::query()->where('action', AuditAction::ClientMerged->value)->where('object_id', $first->id)->orderBy('id')->get();
    expect($moved)->toHaveCount(2)
        ->and($moved->last()?->old_values)->toBe(['merged_into_id' => $duplicate->id, 'merged_into_name' => 'Adecco Italia'])
        ->and($moved->last()?->new_values)->toBe(['merged_into_id' => $keep->id, 'merged_into_name' => 'Adecco Spa']);
});

it('D-359: the merge needs the client that stays', function () {
    $duplicate = Client::factory()->create();

    $this->actingAs(managementAdmin())
        ->post(route('clients.merge', $duplicate), ['keep_id' => '999999'])
        ->assertSessionHasErrors('keep_id');
});

// ---------------------------------------------------------------- Lists and page

it('D-359: the agent no longer sees a merged client; the back office sees it with the client it went into', function () {
    $owner = User::factory()->agent()->create();
    $keep = Client::factory()->named('Adecco Spa')->create();
    $duplicate = Client::factory()->ownedBy($owner)->named('Adecco Italia')->create();
    app(MergeClients::class)->handle($duplicate, $keep, managementAdmin());

    $this->actingAs($owner)
        ->get(PortalPages::url('clients_mine'))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 0));

    // The page of the merged client stays readable to its owner, without naming the other client.
    $this->actingAs($owner)
        ->get(route('clients.edit', $duplicate))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('client.status', 'merged')
            ->where('client.mergedInto', null)
            ->where('management', null));

    $this->actingAs(User::factory()->manager()->create())
        ->get(PortalPages::url('clients'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('clients', 2)
            ->where('clients.0.companyName', 'Adecco Italia')
            ->where('clients.0.status', 'merged')
            ->where('clients.0.mergedInto', 'Adecco Spa')
            ->where('clients.1.status', 'active'));
});

it('D-360: the back-office rows carry the owner and whether the agent is still active; the filter opens from the address', function () {
    $gone = User::factory()->agent()->inactive()->create(['first_name' => 'Gino', 'last_name' => 'Gamma']);
    Client::factory()->ownedBy($gone)->named('Alfa Spa')->create();
    Client::factory()->named('Beta Spa')->create();
    $admin = managementAdmin();

    $this->actingAs($admin)
        ->get(PortalPages::url('clients'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filter.deactivatedOwners', false)
            ->where('clients.0.ownerId', $gone->id)
            ->where('clients.0.ownerActive', false)
            ->where('clients.1.ownerActive', true));

    $this->actingAs($admin)
        ->get(PortalPages::url('clients').'?agenti=disattivati')
        ->assertInertia(fn (Assert $page) => $page->where('filter.deactivatedOwners', true));
});

it('F3-03: the admin page of a client lists only the actions allowed now', function () {
    $owner = managementAgent('Anna', 'Alfa');
    $other = managementAgent('Bruno', 'Beta');
    User::factory()->agent()->inactive()->create();
    $client = Client::factory()->ownedBy($owner)->named('Adecco Spa')->create();
    $target = Client::factory()->named('Beta Srl', '01234567897')->create();
    Client::factory()->named('Gamma Srl')->create(['is_active' => false]);

    $this->actingAs(managementAdmin())
        ->get(route('clients.edit', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('management.reassignUrl', route('clients.reassign', $client))
            ->where('management.deactivateUrl', route('clients.deactivate', $client))
            ->where('management.reactivateUrl', null)
            ->where('management.mergeUrl', route('clients.merge', $client))
            // Active agents except the owner: Bruno Beta and the agent of Beta Srl, never the deactivated one.
            ->where('management.owners', fn ($owners): bool => collect($owners)->pluck('value')->sort()->values()->all()
                === collect([(string) $other->id, (string) $target->owner_id])->sort()->values()->all())
            ->has('management.mergeTargets', 1)
            ->where('management.mergeTargets.0.value', (string) $target->id)
            ->where('management.mergeTargets.0.label', fn (string $label): bool => str_starts_with($label, 'Beta Srl · P.IVA 01234567897 · ')));

    $client->update(['is_active' => false]);
    $this->actingAs(managementAdmin())
        ->get(route('clients.edit', $client))
        ->assertInertia(fn (Assert $page) => $page
            ->where('management.deactivateUrl', null)
            ->where('management.reactivateUrl', route('clients.reactivate', $client)));
});

it('F3-03: the manager sees a client without the admin actions', function () {
    $client = Client::factory()->create();

    $this->actingAs(User::factory()->manager()->create())
        ->get(route('clients.edit', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', false)->where('management', null));
});
