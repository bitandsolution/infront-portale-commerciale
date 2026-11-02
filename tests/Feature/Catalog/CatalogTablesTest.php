<?php

use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\VisibilityType;
use Database\Factories\VisibilityTypeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Tables of the catalogue (D-190, progettazione-tecnica §5.3.3; C3, C9; F2-01): rows inserted directly
| in SQL, as a SQL client would, so the constraints hold for every writer.
*/

const CATALOG_TABLES = ['territories', 'visibility_types', 'inventory_pools', 'led_formats', 'partnership_tiers', 'hospitality_variants', 'benefit_categories'];

/**
 * @param  array<string, mixed>  $overrides
 */
function catalogRawRow(string $table, array $overrides = []): int
{
    $row = ['code' => 'VALORE_PROVA', 'name' => 'Valore di prova', 'created_at' => now(), 'updated_at' => now()];

    if (in_array($table, ['inventory_pools', 'led_formats'], true)) {
        $row['visibility_type_id'] = VisibilityTypeFactory::classic()->id;
    }

    return DB::table($table)->insertGetId(array_merge($row, $overrides));
}

it('D-190: creates the tables of the catalogue with the columns of the data model', function () {
    expect(Schema::getColumnListing('territories'))->toEqualCanonicalizing(['id', 'code', 'name', 'is_active', 'created_at', 'updated_at'])
        ->and(Schema::getColumnListing('visibility_types'))->toEqualCanonicalizing(['id', 'code', 'name', 'is_active', 'created_at', 'updated_at'])
        ->and(Schema::getColumnListing('inventory_pools'))->toEqualCanonicalizing(['id', 'code', 'name', 'visibility_type_id', 'is_active', 'sort_order', 'created_at', 'updated_at'])
        ->and(Schema::getColumnListing('led_formats'))->toEqualCanonicalizing(['id', 'code', 'name', 'description', 'visibility_type_id', 'is_active', 'sort_order', 'created_at', 'updated_at'])
        ->and(Schema::getColumnListing('led_format_pools'))->toEqualCanonicalizing(['id', 'led_format_id', 'inventory_pool_id', 'created_at', 'updated_at'])
        ->and(Schema::getColumnListing('partnership_tiers'))->toEqualCanonicalizing(['id', 'code', 'name', 'is_free_package', 'is_active', 'sort_order', 'created_at', 'updated_at'])
        ->and(Schema::getColumnListing('hospitality_variants'))->toEqualCanonicalizing(['id', 'code', 'name', 'is_active', 'sort_order', 'created_at', 'updated_at'])
        ->and(Schema::getColumnListing('benefit_categories'))->toEqualCanonicalizing(['id', 'code', 'name', 'sort_order', 'created_at', 'updated_at']);

    foreach (CATALOG_TABLES as $table) {
        expect(collect(Schema::getIndexes($table))->firstWhere('columns', ['code'])['unique'] ?? false)->toBeTrue("{$table}.code");
    }

    expect(collect(Schema::getIndexes('led_format_pools'))->firstWhere('columns', ['led_format_id', 'inventory_pool_id'])['unique'] ?? false)->toBeTrue();
});

it('C3: values are referenced with restrictive keys; the pools of a format go with the format', function () {
    $onDelete = fn (string $table): array => collect(Schema::getForeignKeys($table))
        ->mapWithKeys(fn (array $key): array => [$key['columns'][0] => strtolower((string) $key['on_delete'])])
        ->all();

    expect($onDelete('inventory_pools'))->toBe(['visibility_type_id' => 'restrict'])
        ->and($onDelete('led_formats'))->toBe(['visibility_type_id' => 'restrict'])
        ->and($onDelete('led_format_pools'))->toEqualCanonicalizing(['led_format_id' => 'cascade', 'inventory_pool_id' => 'restrict']);

    $format = LedFormat::factory()->create();
    $pool = InventoryPool::factory()->create();
    $format->pools()->attach($pool->id);

    expect(fn () => DB::table('inventory_pools')->where('id', $pool->id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('visibility_types')->where('code', 'CLASSICA')->delete())->toThrow(QueryException::class);

    DB::table('led_formats')->where('id', $format->id)->delete();
    expect(DB::table('led_format_pools')->count())->toBe(0);
});

it('C9: the database refuses codes that are not stable codes and empty names', function (string $table, array $row) {
    expect(fn () => catalogRawRow($table, $row))->toThrow(QueryException::class);
})->with(function (): Generator {
    foreach (CATALOG_TABLES as $table) {
        yield "{$table}: lower-case code" => [$table, ['code' => 'valore_prova']];
        yield "{$table}: code with a space" => [$table, ['code' => 'VALORE PROVA']];
        yield "{$table}: blank name" => [$table, ['name' => '   ']];
    }
});

it('C9: a valid row is accepted and the code is unique', function () {
    foreach (CATALOG_TABLES as $table) {
        catalogRawRow($table, ['code' => 'VALORE_1']);
        expect(fn () => catalogRawRow($table, ['code' => 'VALORE_1']))->toThrow(QueryException::class);
    }

    expect(VisibilityType::query()->where('code', 'VALORE_1')->exists())->toBeTrue();
});

it('D-051: a pool is consumed only once by the same format', function () {
    $format = LedFormat::factory()->create();
    $pool = InventoryPool::factory()->create();
    $format->pools()->attach($pool->id);

    expect(fn () => $format->pools()->attach($pool->id))->toThrow(QueryException::class);
});
