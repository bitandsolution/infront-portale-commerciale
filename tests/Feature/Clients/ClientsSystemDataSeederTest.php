<?php

use App\Models\AuditEvent;
use App\Models\CommoditySector;
use Database\Seeders\ClientsSystemDataSeeder;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Database\QueryException;

/*
| System data of the clients (D-357): the 24 commodity sectors of M05 §6, run again at every release
| (D-295).
*/

it('D-357: seeds the 24 sectors of M05 §6, by the system', function () {
    $this->seed(SystemDataSeeder::class);

    expect(CommoditySector::query()->orderBy('sort_order')->pluck('name')->all())
        ->toBe(array_column(ClientsSystemDataSeeder::SECTORS, 1))
        ->and(CommoditySector::query()->count())->toBe(24)
        ->and(CommoditySector::query()->where('code', 'INDUSTRIA_EDILIZIA_ATTIVITA')->value('name'))->toBe('Industria/Edilizia/Attività')
        ->and(CommoditySector::query()->where('requires_free_text', true)->pluck('code')->all())->toBe(['ALTRO_VARIE'])
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('D-295: running again creates nothing and keeps the changes of the admin', function () {
    $this->seed(SystemDataSeeder::class);

    CommoditySector::query()->where('code', 'BETTING')->update(['name' => 'Scommesse', 'is_active' => false]);

    $this->seed(SystemDataSeeder::class);

    $betting = CommoditySector::query()->where('code', 'BETTING')->sole();
    expect(CommoditySector::query()->count())->toBe(24)
        ->and($betting->name)->toBe('Scommesse')
        ->and($betting->is_active)->toBeFalse();
});

it('C9: the database refuses a code with lower-case letters and an empty name', function (array $row) {
    expect(fn () => CommoditySector::query()->insert($row + ['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
})->with([
    'code in lower case' => [['code' => 'abbigliamento', 'name' => 'Abbigliamento']],
    'empty name' => [['code' => 'VUOTO', 'name' => '   ']],
]);
