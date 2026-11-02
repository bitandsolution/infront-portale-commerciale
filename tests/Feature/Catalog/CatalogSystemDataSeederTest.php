<?php

use App\Domain\Catalog\Actions\UpdateCatalogValue;
use App\Domain\Catalog\Data\CatalogValueData;
use App\Models\AuditEvent;
use App\Models\BenefitCategory;
use App\Models\HospitalityVariant;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Territory;
use App\Models\User;
use App\Models\VisibilityType;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Facades\DB;

/*
| System data of the catalogue (progettazione-tecnica §5.6.1 A1; D-331, D-332), run again at every
| release (D-295).
*/

/**
 * @return array<string, list<string>>
 */
function catalogFormatPools(): array
{
    return LedFormat::query()->with('pools')->orderBy('sort_order')->get()
        ->mapWithKeys(fn (LedFormat $format): array => [$format->code => $format->pools->sortBy('sort_order')->pluck('code')->values()->all()])
        ->all();
}

it('D-332: seeds the values of the catalogue, by the system', function () {
    $this->seed(SystemDataSeeder::class);

    expect(Territory::query()->orderBy('id')->get()->map(fn (Territory $t): array => [$t->code, $t->is_active])->all())
        ->toBe([['ITALIA', true], ['ESTERO', false]])
        ->and(VisibilityType::query()->orderBy('id')->get()->map(fn (VisibilityType $v): array => [$v->code, $v->is_active])->all())
        ->toBe([['CLASSICA', true], ['VIRTUAL', false]])
        ->and(InventoryPool::query()->orderBy('sort_order')->get()->map(fn (InventoryPool $p): array => [$p->code, $p->name, $p->visibilityType->code, $p->is_active])->all())
        ->toBe([
            ['FILA_1', '1ª fila', 'CLASSICA', true],
            ['FILA_2', '2ª fila', 'CLASSICA', true],
            ['CORNER_BASSI', 'Corner bassi', 'CLASSICA', false],
            ['SOTTO_TRIBUNA', 'Sotto tribuna', 'CLASSICA', false],
        ])
        ->and(PartnershipTier::query()->orderBy('sort_order')->get()->map(fn (PartnershipTier $t): array => [$t->code, $t->name, $t->is_free_package])->all())
        ->toBe([
            ['LIBERA', 'Libera', true],
            ['TOP', 'Top', false],
            ['PREMIUM', 'Premium', false],
            ['OFFICIAL', 'Official', false],
            ['OFFICIAL_2', 'Official 2.0', false],
        ])
        ->and(HospitalityVariant::query()->orderBy('sort_order')->pluck('code')->all())->toBe(['PREMIUM', 'BASE'])
        ->and(BenefitCategory::query()->orderBy('sort_order')->pluck('name')->all())->toBe([
            'IP rights',
            'Brand exposure stadio e centro sportivo',
            'Digital & social',
            'Ticketing & hospitality',
            'Esperienze esclusive',
            'Merchandising',
            'Servizi',
            'Note',
        ])
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('D-051, D-332: Jumbo and Domination consume the 1st and 2nd row, the rows their own pool', function () {
    $this->seed(SystemDataSeeder::class);

    expect(catalogFormatPools())->toBe([
        'JUMBO' => ['FILA_1', 'FILA_2'],
        'DOMINATION' => ['FILA_1', 'FILA_2'],
        'FILA_1' => ['FILA_1'],
        'FILA_2' => ['FILA_2'],
    ]);
});

it('D-332: does not create the tier DIGITAL nor the historical formats', function () {
    $this->seed(SystemDataSeeder::class);

    expect(PartnershipTier::query()->where('code', 'DIGITAL')->exists())->toBeFalse()
        ->and(LedFormat::query()->whereIn('code', ['CORNER_BASSI', 'SOTTO_TRIBUNA'])->exists())->toBeFalse()
        ->and(LedFormat::query()->count())->toBe(4);
});

it('D-295: a second run keeps the changes of the admin and adds only what is missing', function () {
    $this->seed(SystemDataSeeder::class);
    $domination = LedFormat::query()->where('code', 'DOMINATION')->firstOrFail();
    app(UpdateCatalogValue::class)->handle($domination, new CatalogValueData(
        name: 'Domination 360', description: 'Tutti i LED dello stadio', sortOrder: 5, isActive: false,
    ), User::factory()->admin()->create());
    $domination->pools()->detach(InventoryPool::query()->where('code', 'FILA_2')->value('id'));
    BenefitCategory::query()->where('code', 'NOTES')->delete();

    $this->seed(SystemDataSeeder::class);

    $domination->refresh();
    expect($domination->name)->toBe('Domination 360')
        ->and($domination->description)->toBe('Tutti i LED dello stadio')
        ->and($domination->sort_order)->toBe(5)
        ->and($domination->is_active)->toBeFalse()
        ->and(catalogFormatPools()['DOMINATION'])->toBe(['FILA_1', 'FILA_2'])
        ->and(BenefitCategory::query()->where('code', 'NOTES')->exists())->toBeTrue()
        ->and(DB::table('led_format_pools')->count())->toBe(6)
        ->and(BenefitCategory::query()->count())->toBe(8);
});
