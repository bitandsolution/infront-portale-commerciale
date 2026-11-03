<?php

use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\InventoryCommitment;
use App\Models\InventoryPool;
use App\Models\PartnershipTier;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Tables of capacities and commitments (M04 §9, RB-12; D-066, D-192, D-334, progettazione-tecnica
| §5.3.3; C3, C9; F2-04): rows written directly in SQL, as a SQL client would, so the constraints and
| the triggers hold for every writer.
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function commitmentRow(array $overrides = []): int
{
    $season = Season::factory()->create();

    return DB::table('inventory_commitments')->insertGetId(array_merge([
        'season_id' => $season->id,
        'club_id' => Club::factory()->infront()->create()->id,
        'proposal_id' => 1,
        'proposal_version_id' => 1,
        'proposal_version_club_id' => 1,
        'resource_type' => 'pool',
        'inventory_pool_id' => InventoryPool::factory()->create()->id,
        'quantity' => 60,
        'starts_on' => $season->starts_on->toDateString(),
        'ends_on' => $season->ends_on->toDateString(),
        'is_forced' => false,
        'created_by' => User::factory()->admin()->create()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('M04 §9: creates the capacity tables with the columns of the data model', function () {
    expect(Schema::getColumnListing('pool_capacities'))->toEqualCanonicalizing(['id', 'season_id', 'club_id', 'inventory_pool_id', 'is_enabled', 'seconds_per_match', 'created_at', 'updated_at'])
        ->and(Schema::getColumnListing('partnership_capacities'))->toEqualCanonicalizing(['id', 'season_id', 'club_id', 'partnership_tier_id', 'is_enabled', 'quantity', 'created_at', 'updated_at'])
        ->and(Schema::getColumnListing('hospitality_capacities'))->toEqualCanonicalizing(['id', 'season_id', 'club_id', 'hospitality_variant_id', 'is_enabled', 'seats', 'created_at', 'updated_at'])
        ->and(Schema::getColumnListing('inventory_commitments'))->toEqualCanonicalizing([
            'id', 'season_id', 'club_id', 'proposal_id', 'proposal_version_id', 'proposal_version_club_id', 'resource_type',
            'inventory_pool_id', 'partnership_tier_id', 'hospitality_variant_id', 'quantity', 'starts_on', 'ends_on',
            'is_forced', 'forced_reason', 'created_by', 'released_at', 'released_by', 'release_reason', 'created_at', 'updated_at',
        ]);
});

it('M04 §9: one capacity per season, club and resource', function (string $table, string $valueKey, Closure $value) {
    $row = ['season_id' => Season::factory()->create()->id, 'club_id' => Club::factory()->infront()->create()->id, $valueKey => $value()->id, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()];
    DB::table($table)->insert($row);

    expect(fn () => DB::table($table)->insert($row))->toThrow(QueryException::class);
})->with([
    'pool' => ['pool_capacities', 'inventory_pool_id', fn () => InventoryPool::factory()->create()],
    'tier' => ['partnership_capacities', 'partnership_tier_id', fn () => PartnershipTier::factory()->create()],
    'variant' => ['hospitality_capacities', 'hospitality_variant_id', fn () => HospitalityVariant::factory()->create()],
]);

it('C9: a commitment has exactly one resource, coherent with its type, a positive quantity and a valid period', function (Closure $overrides) {
    expect(fn () => commitmentRow($overrides()))->toThrow(QueryException::class);
})->with([
    'no resource' => [fn () => ['inventory_pool_id' => null]],
    'two resources' => [fn () => ['partnership_tier_id' => PartnershipTier::factory()->create()->id]],
    'wrong type' => [fn () => ['resource_type' => 'partnership']],
    'zero quantity' => [fn () => ['quantity' => 0]],
    'period reversed' => [fn () => ['starts_on' => '2028-05-31', 'ends_on' => '2027-07-01']],
    'forced without reason' => [fn () => ['is_forced' => true]],
    'reason without forcing' => [fn () => ['forced_reason' => 'Motivo']],
    'released without reason' => [fn () => ['released_at' => now()]],
    'unknown release reason' => [fn () => ['released_at' => now(), 'release_reason' => 'other']],
]);

it('D-192: a commitment is never changed nor deleted, for every database user', function (array $change) {
    $id = commitmentRow();

    expect(fn () => DB::table('inventory_commitments')->where('id', $id)->update($change))->toThrow(QueryException::class, 'immutable');
})->with([
    'quantity' => [['quantity' => 30]],
    'period' => [['ends_on' => '2027-12-31']],
    'forcing' => [['is_forced' => true, 'forced_reason' => 'Motivo']],
    'proposal' => [['proposal_version_id' => 99]],
]);

it('D-192: a commitment cannot be deleted', function () {
    $id = commitmentRow();

    expect(fn () => DB::table('inventory_commitments')->where('id', $id)->delete())->toThrow(QueryException::class, 'never deleted');
});

it('D-192: an open commitment is released once, and then never changed again', function () {
    $id = commitmentRow();
    $admin = User::factory()->admin()->create();

    DB::table('inventory_commitments')->where('id', $id)->update(['released_at' => now(), 'released_by' => $admin->id, 'release_reason' => 'superseded']);

    expect(InventoryCommitment::query()->findOrFail($id)->released_at)->not->toBeNull()
        ->and(fn () => DB::table('inventory_commitments')->where('id', $id)->update(['release_reason' => 'cancelled']))->toThrow(QueryException::class, 'immutable');
});
