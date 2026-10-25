<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| settings (D-191; progettazione-tecnica §5.3.3; C9): rows inserted directly in SQL, as a SQL client
| would, so the constraints are checked for every writer.
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function settingsRawRow(array $overrides = []): int
{
    return DB::table('settings')->insertGetId(array_merge([
        'key' => 'hospitality_max_seats',
        'value' => '6',
        'updated_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('D-191: has key, JSON value and author of the last change as a restrictive foreign key', function () {
    expect(Schema::getColumnListing('settings'))->toEqualCanonicalizing([
        'id', 'key', 'value', 'updated_by', 'created_at', 'updated_at',
    ]);

    $foreignKeys = Schema::getForeignKeys('settings');
    expect($foreignKeys)->toHaveCount(1)
        ->and($foreignKeys[0]['columns'])->toBe(['updated_by'])
        ->and($foreignKeys[0]['foreign_table'])->toBe('users')
        ->and(strtolower((string) $foreignKeys[0]['on_delete']))->toBe('restrict');

    expect(collect(Schema::getIndexes('settings'))->firstWhere('columns', ['key'])['unique'] ?? false)->toBeTrue();
});

it('C9: refuses two rows with the same key', function () {
    settingsRawRow();

    expect(fn () => settingsRawRow(['value' => '4']))->toThrow(QueryException::class);
});

it('C9: accepts only lowercase keys made of letters, digits and underscores', function () {
    $refused = [];

    foreach (['Hospitality_max_seats', 'led-step-seconds', '1_key', 'chiave con spazi', ''] as $key) {
        try {
            settingsRawRow(['key' => $key]);
            $refused[$key] = false;
        } catch (QueryException) {
            $refused[$key] = true;
        }
    }

    expect(array_keys(array_filter($refused, fn (bool $wasRefused): bool => ! $wasRefused)))->toBe([])
        ->and(settingsRawRow(['key' => 'audit_ip_retention_days']))->toBeInt();
});

it('C9: accepts only an integer or a string as value', function () {
    $accepted = [];

    foreach (['[6]', '{"seats": 6}', 'true', 'null', '6.5', 'non json'] as $index => $value) {
        try {
            settingsRawRow(['key' => 'chiave_'.$index, 'value' => $value]);
            $accepted[] = $value;
        } catch (QueryException) {
            // refused, as expected
        }
    }

    expect($accepted)->toBe([])
        ->and(settingsRawRow(['key' => 'led_step_seconds', 'value' => '30']))->toBeInt()
        ->and(settingsRawRow(['key' => 'availability_method', 'value' => '"seasonal"']))->toBeInt();
});

it('C9: refuses an author that is not a user', function () {
    $admin = User::factory()->admin()->create();

    expect(settingsRawRow(['updated_by' => $admin->id]))->toBeInt()
        ->and(fn () => settingsRawRow(['key' => 'led_step_seconds', 'value' => '30', 'updated_by' => $admin->id + 1000]))
        ->toThrow(QueryException::class);
});
