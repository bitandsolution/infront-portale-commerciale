<?php

use App\Enums\AuditAction;
use App\Enums\FilePurpose;
use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\CommoditySector;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Files\SampleFiles;
use App\Support\Navigation\PortalPages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Client registry (M05 F-01, F-03, F-04; D-074…D-081, D-197, D-358, D-361; slice F3-02). Permissions
| of every route are also in the route × role matrix (RouteMatrixTest).
*/

const CLIENTS_DUPLICATE = 'Cliente già presente in anagrafica: contatta l\'amministratore';

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
});

function clientsLogo(string $name = 'logo.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, SampleFiles::png(400, 200));
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clientsForm(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'Rossi Srl',
        'vat_number' => '',
        'email' => 'Info@Rossi.example',
        'contact_name' => 'Mario Rossi',
        'commodity_sector_id' => (string) CommoditySector::factory()->create()->id,
        'sector_free_text' => '',
        'logo' => clientsLogo(),
    ], $overrides);
}

it('M05-CA1: the agent sees only his own clients, the back office sees every client with its agent', function () {
    $agentA = User::factory()->agent()->create(['first_name' => 'Anna', 'last_name' => 'Alfa']);
    $agentB = User::factory()->agent()->create();
    Client::factory()->ownedBy($agentA)->named('Alfa Spa')->create();
    Client::factory()->ownedBy($agentB)->named('Beta Srl')->create();

    $this->actingAs($agentB)
        ->get(PortalPages::url('clients_mine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Index')
            ->has('clients', 1)
            ->where('clients.0.companyName', 'Beta Srl')
            ->where('clients.0.owner', null)
            ->where('withOwner', false)
            ->where('createUrl', route('clients.create')));

    $this->actingAs(User::factory()->manager()->create())
        ->get(PortalPages::url('clients'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('clients', 2)
            ->where('clients.0.companyName', 'Alfa Spa')
            ->where('clients.0.owner', 'Anna Alfa')
            ->where('withOwner', true)
            ->where('createUrl', null));
});

it('M05-CA1, RB-01: the agent cannot open, change or see the logo of a client of another agent', function () {
    $client = Client::factory()->ownedBy(User::factory()->agent()->create())->create();
    $other = User::factory()->agent()->create();

    $this->actingAs($other)->get(route('clients.edit', $client))->assertForbidden();
    $this->actingAs($other)->put(route('clients.update', $client), clientsForm())->assertForbidden();
    $this->actingAs($other)->get(route('files.download', $client->logo_file_id))->assertForbidden();

    expect(Client::query()->visibleTo($other)->count())->toBe(0);
});

it('D-358: the admin, who owns no client, goes from "I miei clienti" to the full list', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(PortalPages::url('clients_mine'))
        ->assertRedirect(PortalPages::url('clients'));
});

it('F-01, FL-02: the agent creates a client of his own, with the logo stored and an audit event', function () {
    $agent = User::factory()->agent()->create();
    $form = clientsForm(['vat_number' => 'IT 0123 4567 897']);

    $this->actingAs($agent)
        ->post(route('clients.store'), $form)
        ->assertRedirect(PortalPages::url('clients_mine'))
        ->assertSessionHas('toast.message', 'Cliente Rossi Srl creato');

    $client = Client::query()->sole();
    expect($client->owner_id)->toBe($agent->id)
        ->and($client->created_by)->toBe($agent->id)
        ->and($client->company_name_normalized)->toBe('ROSSI')
        ->and($client->vat_number)->toBe('01234567897')
        ->and($client->email)->toBe('info@rossi.example')
        ->and($client->sector_free_text)->toBeNull()
        ->and($client->logo->purpose)->toBe(FilePurpose::ClientLogo);

    $event = soleAuditEvent(AuditAction::ClientCreated);
    expect($event->area)->toBe('clients')
        ->and($event->object_label)->toBe('Rossi Srl (P.IVA 01234567897)')
        ->and($event->new_values)->toMatchArray([
            'company_name' => 'Rossi Srl',
            'vat_number' => '01234567897',
            'owner_id' => $agent->id,
            'logo_file_id' => $client->logo_file_id,
        ]);

    $this->actingAs($agent)->get(route('files.download', $client->logo_file_id))->assertOk();
});

it('M05-CA2, RB-02: "Adecco S.p.A." is blocked when "ADECCO" exists, with a neutral message', function () {
    $owner = User::factory()->agent()->create(['first_name' => 'Anna', 'last_name' => 'Alfa']);
    Client::factory()->ownedBy($owner)->named('ADECCO')->create();
    $files = StoredFile::query()->count();

    $this->actingAs(User::factory()->agent()->create())
        ->post(route('clients.store'), clientsForm(['company_name' => 'Adecco S.p.A.']))
        ->assertSessionHasErrors(['duplicate' => CLIENTS_DUPLICATE]);

    expect(Client::query()->count())->toBe(1)
        ->and(StoredFile::query()->count())->toBe($files)
        ->and(auditEventsOf(AuditAction::ClientCreated))->toHaveCount(0)
        ->and(session('errors')->first('duplicate'))->not->toContain('Anna');
});

it('D-082: the admin is told which client is already present and whose it is', function () {
    $owner = User::factory()->agent()->create(['first_name' => 'Anna', 'last_name' => 'Alfa']);
    Client::factory()->ownedBy($owner)->named('ADECCO')->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('clients.store'), clientsForm(['company_name' => 'Adecco Spa', 'owner_id' => (string) User::factory()->agent()->create()->id]))
        ->assertSessionHasErrors(['duplicate' => 'Cliente già presente in anagrafica: ADECCO, dell\'agente Anna Alfa']);
});

it('M05-CA8, D-197: same name with two different VAT numbers is allowed, without a VAT number it is blocked', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->post(route('clients.store'), clientsForm(['company_name' => 'Rossi Srl', 'vat_number' => '01234567897']))->assertSessionHasNoErrors();
    // D-362: the example VAT number of the specification is 09876543217 (09876543210 fails the check digit).
    $this->actingAs($agent)->post(route('clients.store'), clientsForm(['company_name' => 'Rossi Spa', 'vat_number' => '09876543217']))->assertSessionHasNoErrors();
    $this->actingAs($agent)->post(route('clients.store'), clientsForm(['company_name' => 'Rossi Spa', 'vat_number' => '']))
        ->assertSessionHasErrors(['duplicate' => CLIENTS_DUPLICATE]);

    expect(Client::query()->count())->toBe(2);
});

it('RB-03: the same VAT number is a duplicate whatever the name; a new VAT number does not unblock a name without VAT', function () {
    Client::factory()->named('Gamma Srl', '01234567897')->create();
    Client::factory()->named('Delta Srl')->create();
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->post(route('clients.store'), clientsForm(['company_name' => 'Omega Spa', 'vat_number' => '01234567897']))
        ->assertSessionHasErrors(['duplicate' => CLIENTS_DUPLICATE]);
    $this->actingAs($agent)->post(route('clients.store'), clientsForm(['company_name' => 'Delta Spa', 'vat_number' => '09876543217']))
        ->assertSessionHasErrors(['duplicate' => CLIENTS_DUPLICATE]);

    expect(Client::query()->count())->toBe(2);
});

it('RB-03: a deactivated client still counts as a duplicate', function () {
    Client::factory()->named('Epsilon Srl')->create(['is_active' => false]);

    $this->actingAs(User::factory()->agent()->create())
        ->post(route('clients.store'), clientsForm(['company_name' => 'EPSILON']))
        ->assertSessionHasErrors(['duplicate' => CLIENTS_DUPLICATE]);
});

it('M05-CA3, RB-04: with ALTRO/VARIE the free text is required and kept; with another sector it is dropped', function () {
    $agent = User::factory()->agent()->create();
    $other = CommoditySector::factory()->other()->create();

    $this->actingAs($agent)
        ->post(route('clients.store'), clientsForm(['commodity_sector_id' => (string) $other->id, 'sector_free_text' => '']))
        ->assertSessionHasErrors(['sector_free_text' => 'Il campo descrizione del settore è obbligatorio.']);

    $this->actingAs($agent)
        ->post(route('clients.store'), clientsForm(['commodity_sector_id' => (string) $other->id, 'sector_free_text' => '  Droni   e robotica ']))
        ->assertSessionHasNoErrors();
    $this->actingAs($agent)
        ->post(route('clients.store'), clientsForm(['company_name' => 'Zeta Srl', 'sector_free_text' => 'Ignorato']))
        ->assertSessionHasNoErrors();

    expect(Client::query()->where('company_name', 'Rossi Srl')->sole()->sector_free_text)->toBe('Droni e robotica')
        ->and(Client::query()->where('company_name', 'Zeta Srl')->sole()->sector_free_text)->toBeNull();
});

it('M05-CA4: a PDF or a 3 MB logo is refused with a clear message', function (Closure $file, string $message) {
    $this->actingAs(User::factory()->agent()->create())
        ->post(route('clients.store'), clientsForm(['logo' => $file()]))
        ->assertSessionHasErrors(['logo' => $message]);

    expect(Client::query()->count())->toBe(0);
})->with([
    'pdf' => [fn (): UploadedFile => UploadedFile::fake()->createWithContent('logo.pdf', SampleFiles::pdf()), 'Formato non ammesso: carica un file PNG o JPG.'],
    '3 MB' => [fn (): UploadedFile => UploadedFile::fake()->createWithContent('logo.png', SampleFiles::padded(SampleFiles::png(400, 200), 3 * 1024 * 1024)), 'Il file pesa 3 MB: il massimo è 2 MB.'],
]);

it('M05 §6, §8: name, e-mail, sector and logo are required, the VAT number is checked', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->post(route('clients.store'), clientsForm(['company_name' => 'R', 'email' => 'non-una-mail', 'commodity_sector_id' => '', 'logo' => null, 'vat_number' => '09876543210']))
        ->assertSessionHasErrors([
            'company_name',
            'email',
            'commodity_sector_id',
            'logo',
            'vat_number' => 'La P.IVA non è valida: controlla le cifre.',
        ]);

    $this->actingAs(User::factory()->agent()->create())
        ->post(route('clients.store'), clientsForm(['vat_number' => '1234']))
        ->assertSessionHasErrors(['vat_number' => 'La P.IVA è di 11 cifre (si può scrivere anche con il prefisso IT).']);
});

it('RB-08: a deactivated sector is not offered and not accepted for a new client', function () {
    $closed = CommoditySector::factory()->create(['is_active' => false]);

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('clients.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Form')
            ->where('owners', null)
            ->where('sectors', fn ($sectors): bool => collect($sectors)->doesntContain('value', (string) $closed->id)));

    $this->actingAs(User::factory()->agent()->create())
        ->post(route('clients.store'), clientsForm(['commodity_sector_id' => (string) $closed->id]))
        ->assertSessionHasErrors('commodity_sector_id');
});

it('D-358: the admin creates a client for an active agent he chooses; the owner is required', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();

    $this->actingAs($admin)
        ->get(route('clients.create'))
        ->assertInertia(fn (Assert $page) => $page->has('owners', 1)->where('owners.0.value', (string) $agent->id));

    $this->actingAs($admin)->post(route('clients.store'), clientsForm())->assertSessionHasErrors('owner_id');
    $this->actingAs($admin)->post(route('clients.store'), clientsForm(['owner_id' => (string) User::factory()->manager()->create()->id]))->assertSessionHasErrors('owner_id');
    $this->actingAs($admin)->post(route('clients.store'), clientsForm(['owner_id' => (string) User::factory()->agent()->inactive()->create()->id]))->assertSessionHasErrors('owner_id');

    $this->actingAs($admin)
        ->post(route('clients.store'), clientsForm(['owner_id' => (string) $agent->id]))
        ->assertRedirect(PortalPages::url('clients'));

    $client = Client::query()->sole();
    expect($client->owner_id)->toBe($agent->id)
        ->and($client->created_by)->toBe($admin->id);
});

it('F-04: the owner agent changes his client; the audit keeps only the changed fields', function () {
    $agent = User::factory()->agent()->create();
    $client = Client::factory()->ownedBy($agent)->named('Rossi Srl')->create(['email' => 'info@rossi.example']);

    $this->actingAs($agent)
        ->put(route('clients.update', $client), clientsForm([
            'company_name' => 'Rossi Srl',
            'email' => 'commerciale@rossi.example',
            'commodity_sector_id' => (string) $client->commodity_sector_id,
            'contact_name' => '',
            'logo' => null,
        ]))
        ->assertSessionHas('toast.message', 'Cliente Rossi Srl salvato');

    $event = soleAuditEvent(AuditAction::ClientUpdated);
    expect($event->old_values)->toBe(['email' => 'info@rossi.example'])
        ->and($event->new_values)->toBe(['email' => 'commerciale@rossi.example']);
});

it('F-04: saving the same values writes nothing', function () {
    $agent = User::factory()->agent()->create();
    $client = Client::factory()->ownedBy($agent)->named('Rossi Srl')->create(['email' => 'info@rossi.example', 'contact_name' => 'Mario Rossi']);

    $this->actingAs($agent)
        ->put(route('clients.update', $client), clientsForm(['commodity_sector_id' => (string) $client->commodity_sector_id, 'logo' => null]))
        ->assertSessionHas('toast.message', 'Nessuna modifica da salvare');

    expect(AuditEvent::query()->count())->toBe(0);
});

it('M05-CA5 (F3 part), RB-07: a new logo is a new file, the previous one stays as it was', function () {
    $agent = User::factory()->agent()->create();
    $client = Client::factory()->ownedBy($agent)->named('Rossi Srl')->create();
    $previous = $client->logo;

    $this->actingAs($agent)
        ->put(route('clients.update', $client), clientsForm([
            'email' => $client->email,
            'contact_name' => '',
            'commodity_sector_id' => (string) $client->commodity_sector_id,
            'logo' => UploadedFile::fake()->createWithContent('nuovo.jpg', SampleFiles::jpeg(500, 300)),
        ]))
        ->assertSessionHasNoErrors();

    $client->refresh();
    expect($client->logo_file_id)->not->toBe($previous->id)
        ->and($previous->fresh()?->sha256)->toBe($previous->sha256)
        ->and(Storage::disk(StoredFile::DISK)->exists($previous->path))->toBeTrue();

    $event = soleAuditEvent(AuditAction::ClientUpdated);
    expect($event->old_values)->toBe(['logo_file_id' => $previous->id])
        ->and($event->new_values)->toBe(['logo_file_id' => $client->logo_file_id]);
});

it('RB-02: a change that makes the client a duplicate of another is blocked', function () {
    $agent = User::factory()->agent()->create();
    Client::factory()->named('Alfa Spa')->create();
    $client = Client::factory()->ownedBy($agent)->named('Beta Srl')->create();

    $this->actingAs($agent)
        ->put(route('clients.update', $client), clientsForm(['company_name' => 'ALFA', 'commodity_sector_id' => (string) $client->commodity_sector_id, 'logo' => null]))
        ->assertSessionHasErrors(['duplicate' => CLIENTS_DUPLICATE]);

    expect($client->fresh()?->company_name)->toBe('Beta Srl');
});

it('RB-08: a client keeps its deactivated sector but cannot move to another deactivated one', function () {
    $agent = User::factory()->agent()->create();
    $client = Client::factory()->ownedBy($agent)->named('Rossi Srl')->create();
    $client->sector->update(['is_active' => false]);
    $closed = CommoditySector::factory()->create(['is_active' => false]);

    $this->actingAs($agent)
        ->put(route('clients.update', $client), clientsForm(['commodity_sector_id' => (string) $client->commodity_sector_id, 'email' => 'nuova@rossi.example', 'logo' => null]))
        ->assertSessionHasNoErrors();
    $this->actingAs($agent)
        ->put(route('clients.update', $client), clientsForm(['commodity_sector_id' => (string) $closed->id, 'logo' => null]))
        ->assertSessionHasErrors('commodity_sector_id');
});

it('D-076: the manager sees a client and its logo read-only; the admin can change it', function () {
    $client = Client::factory()->named('Rossi Srl')->create();

    $this->actingAs(User::factory()->manager()->create())
        ->get(route('clients.edit', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Form')
            ->where('canEdit', false)
            ->where('saveUrl', null)
            ->where('client.companyName', 'Rossi Srl'));
    $this->actingAs(User::factory()->manager()->create())
        ->get(route('files.download', $client->logo_file_id))
        ->assertOk();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('clients.edit', $client))
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', true)->where('saveUrl', route('clients.update', $client)));
});
