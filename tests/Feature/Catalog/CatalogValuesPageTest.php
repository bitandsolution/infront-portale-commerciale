<?php

use App\Enums\AuditAction;
use App\Enums\AuditArea;
use App\Models\AuditEvent;
use App\Models\BenefitCategory;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Database\Seeders\SystemDataSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Page "Catalogo › Valori" (M03 F-01…F-04, F-06; D-331): admin only. Permissions of every route are
| in the route × role matrix (RouteMatrixTest).
*/

function catalogValue(string $model, string $code): LedFormat|PartnershipTier|HospitalityVariant|BenefitCategory
{
    /** @var class-string<LedFormat|PartnershipTier|HospitalityVariant|BenefitCategory> $model */
    return $model::query()->where('code', $code)->firstOrFail();
}

beforeEach(function () {
    $this->seed(SystemDataSeeder::class);
});

it('D-331: the admin sees the values of the catalogue in their order, the system ones read-only', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(PortalPages::url('catalog_values'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Values/Index')
            ->where('layout.title', 'Valori')
            ->has('territories', 2)
            ->where('territories.1', ['code' => 'ESTERO', 'name' => 'Estero', 'isActive' => false])
            ->has('visibilityTypes', 2)
            ->has('pools', 4)
            ->where('pools.2.code', 'CORNER_BASSI')
            ->where('pools.2.visibilityType', 'Classica')
            ->has('formats', 4)
            ->where('formats.1.code', 'DOMINATION')
            ->where('formats.1.notes', ['Consuma: 1ª fila + 2ª fila'])
            ->where('formats.1.url', route('catalog.values.formats.edit', catalogValue(LedFormat::class, 'DOMINATION')))
            ->has('tiers', 5)
            ->where('tiers.0.code', 'LIBERA')
            ->where('tiers.0.notes', ['Pacchetto Libera: senza prezzo, senza benefit e senza contatore'])
            ->where('tiers.1.notes', [])
            ->has('variants', 2)
            ->has('categories', 8)
            ->where('categories.0.isActive', null)
            ->where('categories.0.description', null));
});

it('D-331: the page of a format shows the code and the pools, with description and active', function () {
    $format = catalogValue(LedFormat::class, 'JUMBO');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catalog.values.formats.edit', $format))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Values/Form')
            ->where('value.code', 'JUMBO')
            ->where('kindLabel', 'Format LED')
            ->where('hasDescription', true)
            ->where('hasActiveFlag', true)
            ->where('saveUrl', route('catalog.values.formats.update', $format)));
});

it('D-331, D-127: the admin renames, describes, reorders and deactivates a format, with an audit event', function () {
    $format = catalogValue(LedFormat::class, 'DOMINATION');

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('catalog.values.formats.update', $format), [
            'name' => 'Domination 360',
            'description' => '  Tutti i LED dello stadio  ',
            'sort_order' => '15',
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', 'Valore Domination 360 salvato');

    $format->refresh();
    expect($format->name)->toBe('Domination 360')
        ->and($format->description)->toBe('Tutti i LED dello stadio')
        ->and($format->sort_order)->toBe(15)
        ->and($format->is_active)->toBeFalse()
        ->and($format->code)->toBe('DOMINATION')
        ->and($format->pools()->count())->toBe(2);

    $event = soleAuditEvent(AuditAction::LedFormatUpdated);
    expect($event->area)->toBe('catalog')
        ->and($event->user_role)->toBe('admin')
        ->and($event->object_type)->toBe('led_format')
        ->and($event->object_label)->toBe('Domination 360 (DOMINATION)')
        ->and($event->old_values)->toBe(['name' => 'Domination', 'description' => null, 'is_active' => true, 'sort_order' => 20])
        ->and($event->new_values)->toBe(['name' => 'Domination 360', 'description' => 'Tutti i LED dello stadio', 'is_active' => false, 'sort_order' => 15]);
});

it('D-331: an empty description is saved as no description', function () {
    $format = catalogValue(LedFormat::class, 'JUMBO');
    $format->update(['description' => 'Testo da togliere']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('catalog.values.formats.update', $format), ['name' => 'Jumbo', 'description' => '', 'sort_order' => '10', 'is_active' => true])
        ->assertSessionHasNoErrors();

    expect($format->refresh()->description)->toBeNull();
});

it('D-331, D-332: the free-package flag and the code of the Libera never change from the portal', function () {
    $libera = catalogValue(PartnershipTier::class, 'LIBERA');

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('catalog.values.tiers.update', $libera), [
            'name' => 'Pacchetto libero',
            'sort_order' => '5',
            'is_active' => true,
            'is_free_package' => false,
            'code' => 'LIBERO',
            'description' => 'Ignorata: i livelli non hanno descrizione',
        ])
        ->assertSessionHasNoErrors();

    $libera->refresh();
    expect($libera->name)->toBe('Pacchetto libero')
        ->and($libera->code)->toBe('LIBERA')
        ->and($libera->is_free_package)->toBeTrue()
        ->and(soleAuditEvent(AuditAction::PartnershipTierUpdated)->new_values)->toBe(['name' => 'Pacchetto libero', 'sort_order' => 5]);
});

it('D-331: a hospitality variant is deactivated', function () {
    $base = catalogValue(HospitalityVariant::class, 'BASE');

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('catalog.values.variants.update', $base), ['name' => 'Base', 'sort_order' => '20', 'is_active' => false])
        ->assertSessionHas('toast.message', 'Valore Base salvato');

    expect($base->refresh()->is_active)->toBeFalse()
        ->and(soleAuditEvent(AuditAction::HospitalityVariantUpdated)->old_values)->toBe(['is_active' => true]);
});

it('D-331: a benefit category has only name and order', function () {
    $notes = catalogValue(BenefitCategory::class, 'NOTES');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('catalog.values.categories.edit', $notes))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasDescription', false)
            ->where('hasActiveFlag', false));

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('catalog.values.categories.update', $notes), ['name' => 'Note ed esclusività', 'sort_order' => '90'])
        ->assertSessionHasNoErrors();

    expect($notes->refresh()->name)->toBe('Note ed esclusività')
        ->and(soleAuditEvent(AuditAction::BenefitCategoryUpdated)->new_values)->toBe(['name' => 'Note ed esclusività', 'sort_order' => 90]);
});

it('D-331: name and order are required, the active flag where the kind has it', function () {
    $format = catalogValue(LedFormat::class, 'FILA_1');

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('catalog.values.formats.update', $format), ['name' => '', 'sort_order' => 'primo'])
        ->assertSessionHasErrors([
            'name' => 'Il campo nome è obbligatorio.',
            'sort_order' => 'Il campo ordine deve essere un numero intero.',
            'is_active' => 'Il campo attivo è obbligatorio.',
        ]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('catalog.values.formats.update', $format), ['name' => 'Fila uno', 'sort_order' => '10000', 'is_active' => true])
        ->assertSessionHasErrors(['sort_order' => 'Il campo ordine deve essere compreso tra 0 e 9999.']);

    expect($format->refresh()->name)->toBe('1ª fila')
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('D-331: saving the same values writes nothing', function () {
    $premium = catalogValue(PartnershipTier::class, 'PREMIUM');

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('catalog.values.tiers.update', $premium), ['name' => 'Premium', 'sort_order' => '30', 'is_active' => true])
        ->assertSessionHas('toast.message', 'Nessuna modifica da salvare');

    expect(AuditEvent::query()->count())->toBe(0);
});

it('D-337: the events of the catalogue are in the area "catalog", with an Italian label', function (AuditAction $action) {
    expect($action->area())->toBe(AuditArea::Catalog)
        ->and($action->label())->not->toBe($action->value)
        ->and(AuditArea::Catalog->label())->toBe('Catalogo e listini');
})->with([
    AuditAction::LedFormatUpdated,
    AuditAction::PartnershipTierUpdated,
    AuditAction::HospitalityVariantUpdated,
    AuditAction::BenefitCategoryUpdated,
]);
