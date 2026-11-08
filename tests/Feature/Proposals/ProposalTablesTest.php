<?php

use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\StoredFile;
use App\Models\Territory;
use App\Models\User;
use App\Models\VisibilityType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
| Tables of the proposals (D-198, D-199, D-371, D-376, progettazione-tecnica §5.3.4; C3, C9; F4-01):
| rows written directly in SQL, as a SQL client would, so the constraints and the triggers hold for
| every writer, beyond the models.
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
});

/**
 * A draft proposal with its working version, one club row and two hospitality seats, written in SQL.
 *
 * @return array{proposal: int, version: int, row: int, seat: int}
 */
function proposalsDraftRows(): array
{
    $proposal = Proposal::factory()->create();
    $now = now();

    $version = DB::table('proposal_versions')->insertGetId(['proposal_id' => $proposal->id, 'list_total' => '0.00', 'created_at' => $now, 'updated_at' => $now]);
    $row = DB::table('proposal_version_clubs')->insertGetId([
        'proposal_version_id' => $version,
        'club_id' => Club::factory()->infront()->create()->id,
        'territory_id' => Territory::factory()->create()->id,
        'visibility_type_id' => VisibilityType::factory()->create()->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $seat = DB::table('proposal_version_club_seats')->insertGetId([
        'proposal_version_club_id' => $row,
        'hospitality_variant_id' => HospitalityVariant::factory()->create()->id,
        'seats' => 2,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return ['proposal' => $proposal->id, 'version' => $version, 'row' => $row, 'seat' => $seat];
}

/**
 * Freezes a working version as F5 will do at the approval: number, frozen_at and frozen_by.
 */
function proposalsFreeze(int $version, int $number = 1): void
{
    DB::table('proposal_versions')->where('id', $version)->update([
        'number' => $number,
        'frozen_at' => now(),
        'frozen_by' => User::factory()->admin()->create()->id,
    ]);
}

it('D-376: creates the proposal tables with every column of the data model', function () {
    expect(Schema::getColumnListing('proposals'))->toEqualCanonicalizing([
        'id', 'number', 'agent_id', 'client_id', 'season_id', 'status', 'current_version_id', 'submitted_at', 'decided_at',
        'decided_by', 'last_rejection_reason', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'review_locked_by',
        'review_locked_at', 'duplicated_from_id', 'lock_version', 'created_at', 'updated_at',
    ])->and(Schema::getColumnListing('proposal_versions'))->toEqualCanonicalizing([
        'id', 'proposal_id', 'number', 'working_slot', 'frozen_at', 'frozen_by', 'change_note', 'starts_on', 'ends_on',
        'client_name', 'client_logo_file_id', 'commodity_sector_id', 'sector_free_text', 'list_total', 'discount_type',
        'discount_value', 'discount_amount', 'final_total', 'campaign_starts_on', 'campaign_months', 'total_matches',
        'total_spots', 'top_matches', 'is_forced', 'forced_reason', 'is_legacy', 'snapshot', 'created_at', 'updated_at',
    ])->and(Schema::getColumnListing('proposal_version_clubs'))->toEqualCanonicalizing([
        'id', 'proposal_version_id', 'club_id', 'competition_season_id', 'sort_order', 'territory_id', 'visibility_type_id',
        'partnership_tier_id', 'led_format_id', 'led_seconds_per_match', 'home_matches', 'matches_source', 'price_list_id',
        'led_unit_price', 'led_price', 'partnership_price', 'hospitality_price', 'club_total', 'created_at', 'updated_at',
    ])->and(Schema::getColumnListing('proposal_version_club_seats'))->toEqualCanonicalizing([
        'id', 'proposal_version_club_id', 'hospitality_variant_id', 'seats', 'seat_price', 'price', 'created_at', 'updated_at',
    ])->and(Schema::getColumnListing('proposal_events'))->toEqualCanonicalizing([
        'id', 'proposal_id', 'proposal_version_id', 'type', 'user_id', 'notes', 'created_at', 'updated_at',
    ]);
});

it('C3: restrictive keys, except the pure children of a draft and the source of a duplicate', function () {
    $onDelete = fn (string $table): array => collect(Schema::getForeignKeys($table))
        ->mapWithKeys(fn (array $key): array => [$key['columns'][0] => strtolower((string) $key['on_delete'])])
        ->all();

    expect($onDelete('proposals'))->toEqualCanonicalizing([
        'agent_id' => 'restrict', 'client_id' => 'restrict', 'season_id' => 'restrict', 'current_version_id' => 'restrict',
        'decided_by' => 'restrict', 'cancelled_by' => 'restrict', 'review_locked_by' => 'restrict', 'duplicated_from_id' => 'set null',
    ])->and($onDelete('proposal_versions'))->toEqualCanonicalizing([
        'proposal_id' => 'cascade', 'frozen_by' => 'restrict', 'client_logo_file_id' => 'restrict', 'commodity_sector_id' => 'restrict',
    ])->and($onDelete('proposal_version_clubs'))->toEqualCanonicalizing([
        'proposal_version_id' => 'cascade', 'club_id' => 'restrict', 'competition_season_id' => 'restrict', 'territory_id' => 'restrict',
        'visibility_type_id' => 'restrict', 'partnership_tier_id' => 'restrict', 'led_format_id' => 'restrict', 'price_list_id' => 'restrict',
    ])->and($onDelete('proposal_version_club_seats'))->toEqualCanonicalizing([
        'proposal_version_club_id' => 'cascade', 'hospitality_variant_id' => 'restrict',
    ])->and($onDelete('proposal_events'))->toEqualCanonicalizing([
        'proposal_id' => 'cascade', 'proposal_version_id' => 'set null', 'user_id' => 'restrict',
    ]);
});

it('D-371: the sequence of the numbers starts from 204, one by one, without cache', function () {
    $sequence = DB::selectOne('SELECT start_value, increment, cache_size, cycle_option FROM proposal_numbers');

    expect((int) $sequence->start_value)->toBe(204)
        ->and((int) $sequence->increment)->toBe(1)
        ->and((int) $sequence->cache_size)->toBe(0)
        ->and((int) $sequence->cycle_option)->toBe(0);
});

it('M07 RB-09: the number of a proposal is unique', function () {
    $proposal = Proposal::factory()->create();

    expect(fn () => Proposal::factory()->create(['number' => $proposal->number]))->toThrow(QueryException::class);
});

it('D-376: the status has no "historical" and only the five values of the life cycle', function (string $status, bool $valid) {
    $proposal = Proposal::factory()->create();
    $update = fn () => DB::table('proposals')->where('id', $proposal->id)->update(['status' => $status]);

    $valid ? expect($update())->toBe(1) : expect($update)->toThrow(QueryException::class);
})->with([
    'submitted' => ['submitted', true],
    'cancelled' => ['cancelled', true],
    'historical' => ['historical', false],
    'unknown' => ['archived', false],
]);

it('§5.3.4: client and season are null only in a draft', function () {
    $proposal = Proposal::factory()->create(['client_id' => null, 'season_id' => null]);

    expect($proposal->client_id)->toBeNull()
        ->and(fn () => DB::table('proposals')->where('id', $proposal->id)->update(['status' => 'submitted']))->toThrow(QueryException::class);
});

it('C9: the pairs of the life cycle are both null or both set', function (array $change) {
    $proposal = Proposal::factory()->create();

    expect(fn () => DB::table('proposals')->where('id', $proposal->id)->update($change))->toThrow(QueryException::class);
})->with([
    'decision without author' => [['decided_at' => '2027-06-01 10:00:00']],
    'cancellation without author' => [['cancelled_at' => '2027-06-01 10:00:00']],
    'review lock without time' => [fn () => ['review_locked_by' => User::factory()->manager()->create()->id]],
]);

it('D-198: a proposal has a single working version, and numbered frozen versions', function () {
    $rows = proposalsDraftRows();
    $insert = fn (array $values = []) => DB::table('proposal_versions')->insertGetId(array_merge(
        ['proposal_id' => $rows['proposal'], 'list_total' => '0.00', 'created_at' => now(), 'updated_at' => now()],
        $values,
    ));

    expect($insert)->toThrow(QueryException::class);

    proposalsFreeze($rows['version']);
    proposalsFreeze($insert(), 2);
    $third = $insert();

    expect(ProposalVersion::query()->where('proposal_id', $rows['proposal'])->whereNotNull('number')->orderBy('number')->pluck('number')->all())->toBe([1, 2])
        ->and(fn () => proposalsFreeze($third, 2))->toThrow(QueryException::class);
});

it('C9: a version is frozen with number, time and author together; discount and totals are coherent', function (array $values) {
    $rows = proposalsDraftRows();

    expect(fn () => DB::table('proposal_versions')->where('id', $rows['version'])->update($values))->toThrow(QueryException::class);
})->with([
    'number without freezing' => [['number' => 1]],
    'freezing without number' => [fn () => ['frozen_at' => '2027-06-01 10:00:00', 'frozen_by' => User::factory()->admin()->create()->id]],
    'percentage over 100' => [['list_total' => '1000.00', 'discount_type' => 'percent', 'discount_value' => '100.01', 'discount_amount' => '10.00']],
    'discount over the list total' => [['list_total' => '1000.00', 'discount_type' => 'amount', 'discount_value' => '1000.01', 'discount_amount' => '1000.01']],
    'discount without amount' => [['list_total' => '1000.00', 'discount_type' => 'amount', 'discount_value' => '10.00']],
    'unknown discount type' => [['list_total' => '1000.00', 'discount_type' => 'gift', 'discount_value' => '10.00', 'discount_amount' => '10.00']],
    'negative final total' => [['final_total' => '-0.01']],
    'period reversed' => [['starts_on' => '2027-09-01', 'ends_on' => '2027-08-31']],
    'forced without reason' => [['is_forced' => true]],
]);

it('D-088, C6: a club row has one LED format with its seconds, both or neither', function (array $values) {
    $rows = proposalsDraftRows();

    expect(fn () => DB::table('proposal_version_clubs')->where('id', $rows['row'])->update($values))->toThrow(QueryException::class);
})->with([
    'format without seconds' => [fn () => ['led_format_id' => LedFormat::factory()->create()->id]],
    'seconds without format' => [['led_seconds_per_match' => 60]],
    'zero seconds' => [fn () => ['led_format_id' => LedFormat::factory()->create()->id, 'led_seconds_per_match' => 0]],
    'unknown source of the matches' => [['matches_source' => 'guess']],
    'negative price' => [['club_total' => '-1.00']],
]);

it('D-147: a club row has 1 to 6 seats of a variant, once', function (int $seats) {
    $rows = proposalsDraftRows();

    expect(fn () => DB::table('proposal_version_club_seats')->where('id', $rows['seat'])->update(['seats' => $seats]))->toThrow(QueryException::class);
})->with([0, 7]);

it('D-199: the history accepts only the types of M07 F-10', function () {
    $rows = proposalsDraftRows();

    expect(fn () => DB::table('proposal_events')->insert([
        'proposal_id' => $rows['proposal'], 'type' => 'viewed', 'user_id' => User::factory()->agent()->create()->id,
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('M03-CA6, D-198: a frozen version is never changed nor deleted, for every database user', function (array $change) {
    $rows = proposalsDraftRows();
    proposalsFreeze($rows['version']);

    expect(fn () => DB::table('proposal_versions')->where('id', $rows['version'])->update($change))->toThrow(QueryException::class, 'immutable')
        ->and(fn () => DB::table('proposal_versions')->where('id', $rows['version'])->delete())->toThrow(QueryException::class, 'immutable');
})->with([
    'list total' => [['list_total' => '1.00']],
    'period' => [['ends_on' => '2027-12-31']],
    'unfreezing' => [['number' => null, 'frozen_at' => null, 'frozen_by' => null]],
]);

it('M03-CA6, D-198: the club rows and the seats of a frozen version are never changed, deleted or added', function () {
    $rows = proposalsDraftRows();
    proposalsFreeze($rows['version']);
    $variant = HospitalityVariant::factory()->create()->id;

    expect(fn () => DB::table('proposal_version_clubs')->where('id', $rows['row'])->update(['club_total' => '100.00']))->toThrow(QueryException::class, 'immutable')
        ->and(fn () => DB::table('proposal_version_clubs')->where('id', $rows['row'])->delete())->toThrow(QueryException::class, 'immutable')
        ->and(fn () => DB::table('proposal_version_clubs')->insert([
            'proposal_version_id' => $rows['version'], 'club_id' => Club::factory()->infront()->create()->id,
            'territory_id' => Territory::factory()->create()->id, 'visibility_type_id' => VisibilityType::factory()->create()->id,
        ]))->toThrow(QueryException::class, 'immutable')
        ->and(fn () => DB::table('proposal_version_club_seats')->where('id', $rows['seat'])->update(['seats' => 3]))->toThrow(QueryException::class, 'immutable')
        ->and(fn () => DB::table('proposal_version_club_seats')->where('id', $rows['seat'])->delete())->toThrow(QueryException::class, 'immutable')
        ->and(fn () => DB::table('proposal_version_club_seats')->insert([
            'proposal_version_club_id' => $rows['row'], 'hospitality_variant_id' => $variant, 'seats' => 1,
        ]))->toThrow(QueryException::class, 'immutable');
});

it('D-198: a row of a working version cannot move into or out of a frozen version', function () {
    $frozen = proposalsDraftRows();
    proposalsFreeze($frozen['version']);
    $working = proposalsDraftRows();

    expect(fn () => DB::table('proposal_version_clubs')->where('id', $working['row'])->update(['proposal_version_id' => $frozen['version']]))->toThrow(QueryException::class, 'immutable')
        ->and(fn () => DB::table('proposal_version_clubs')->where('id', $frozen['row'])->update(['proposal_version_id' => $working['version']]))->toThrow(QueryException::class, 'immutable')
        ->and(fn () => DB::table('proposal_version_club_seats')->where('id', $working['seat'])->update(['proposal_version_club_id' => $frozen['row']]))->toThrow(QueryException::class, 'immutable');
});

it('D-165: the working version and its rows stay editable', function () {
    $rows = proposalsDraftRows();

    expect(DB::table('proposal_versions')->where('id', $rows['version'])->update(['list_total' => '1500.00']))->toBe(1)
        ->and(DB::table('proposal_version_clubs')->where('id', $rows['row'])->update(['club_total' => '1500.00']))->toBe(1)
        ->and(DB::table('proposal_version_club_seats')->where('id', $rows['seat'])->update(['seats' => 6]))->toBe(1)
        ->and(DB::table('proposal_version_club_seats')->where('id', $rows['seat'])->delete())->toBe(1)
        ->and(DB::table('proposal_version_clubs')->where('id', $rows['row'])->delete())->toBe(1);
});

it('M07 RB-08: deleting a draft removes its working version, rows, seats and history', function () {
    $rows = proposalsDraftRows();
    DB::table('proposal_events')->insert([
        'proposal_id' => $rows['proposal'], 'proposal_version_id' => $rows['version'], 'type' => 'created',
        'user_id' => User::factory()->agent()->create()->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(DB::table('proposals')->where('id', $rows['proposal'])->delete())->toBe(1)
        ->and(DB::table('proposal_versions')->where('id', $rows['version'])->exists())->toBeFalse()
        ->and(DB::table('proposal_version_clubs')->where('id', $rows['row'])->exists())->toBeFalse()
        ->and(DB::table('proposal_version_club_seats')->where('id', $rows['seat'])->exists())->toBeFalse()
        ->and(DB::table('proposal_events')->where('proposal_id', $rows['proposal'])->exists())->toBeFalse();
});

it('M07 RB-08: a proposal that is not a draft, or has a frozen version, is never deleted', function (bool $freeze, string $status) {
    $rows = proposalsDraftRows();
    if ($freeze) {
        proposalsFreeze($rows['version']);
    }
    DB::table('proposals')->where('id', $rows['proposal'])->update(['status' => $status]);

    expect(fn () => DB::table('proposals')->where('id', $rows['proposal'])->delete())->toThrow(QueryException::class, 'never deleted')
        ->and(DB::table('proposal_versions')->where('id', $rows['version'])->exists())->toBeTrue();
})->with([
    'submitted' => [false, 'submitted'],
    'approved' => [true, 'approved'],
    'cancelled' => [true, 'cancelled'],
    'draft with a frozen version' => [true, 'draft'],
]);

it('D-199: an entry of the history is never changed nor deleted', function () {
    $rows = proposalsDraftRows();
    $event = DB::table('proposal_events')->insertGetId([
        'proposal_id' => $rows['proposal'], 'type' => 'created', 'user_id' => User::factory()->agent()->create()->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => DB::table('proposal_events')->where('id', $event)->update(['notes' => 'Modificata']))->toThrow(QueryException::class, 'never changed')
        ->and(fn () => DB::table('proposal_events')->where('id', $event)->delete())->toThrow(QueryException::class, 'never deleted');
});

it('D-091: deleting the source draft keeps its duplicate', function () {
    $source = Proposal::factory()->create();
    $copy = Proposal::factory()->create(['duplicated_from_id' => $source->id]);

    $source->delete();

    expect($copy->fresh()?->duplicated_from_id)->toBeNull();
});
