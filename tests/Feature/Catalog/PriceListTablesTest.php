<?php

use App\Models\Club;
use App\Models\LedFormat;
use App\Models\PriceList;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Tables of the price lists (D-190, D-333, progettazione-tecnica §5.3.3; C3, C5, C9; F2-02): rows
| written directly in SQL, as a SQL client would, so the constraints hold for every writer.
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function priceListRawRow(array $overrides = []): int
{
    return DB::table('price_lists')->insertGetId(array_merge([
        'season_id' => Season::factory()->create()->id,
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function ledPriceRawRow(int $priceListId, array $overrides = []): int
{
    return DB::table('led_prices')->insertGetId(array_merge([
        'price_list_id' => $priceListId,
        'club_id' => Club::factory()->infront()->create()->id,
        'led_format_id' => LedFormat::factory()->create()->id,
        'unit_price' => '3157.89',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('D-190: creates price_lists and led_prices with the columns of the data model', function () {
    expect(Schema::getColumnListing('price_lists'))->toEqualCanonicalizing([
        'id', 'season_id', 'status', 'published_at', 'published_by', 'copied_from_id', 'copy_increase_pct', 'created_at', 'updated_at',
    ])->and(Schema::getColumnListing('led_prices'))->toEqualCanonicalizing([
        'id', 'price_list_id', 'club_id', 'led_format_id', 'unit_price', 'created_at', 'updated_at',
    ]);

    expect(collect(Schema::getIndexes('price_lists'))->firstWhere('columns', ['season_id'])['unique'] ?? false)->toBeTrue()
        ->and(collect(Schema::getIndexes('led_prices'))->firstWhere('columns', ['price_list_id', 'club_id', 'led_format_id'])['unique'] ?? false)->toBeTrue();
});

it('D-190: one price list per season, one price per club and format', function () {
    $season = Season::factory()->create();
    $id = priceListRawRow(['season_id' => $season->id]);

    expect(fn () => priceListRawRow(['season_id' => $season->id]))->toThrow(QueryException::class);

    $club = Club::factory()->infront()->create();
    $format = LedFormat::factory()->create();
    ledPriceRawRow($id, ['club_id' => $club->id, 'led_format_id' => $format->id]);

    expect(fn () => ledPriceRawRow($id, ['club_id' => $club->id, 'led_format_id' => $format->id, 'unit_price' => '1.00']))->toThrow(QueryException::class);
});

it('C5, C9: prices are exact decimals, never negative', function () {
    $priceList = PriceList::factory()->create();
    $id = ledPriceRawRow($priceList->id, ['unit_price' => '3157.89']);

    expect(DB::table('led_prices')->where('id', $id)->value('unit_price'))->toBe('3157.89')
        ->and(fn () => ledPriceRawRow($priceList->id, ['unit_price' => '-0.01']))->toThrow(QueryException::class);

    ledPriceRawRow($priceList->id, ['unit_price' => '0.00']);
    expect(DB::table('led_prices')->count())->toBe(2);
});

it('C9, D-333: the status is draft or published, and published exactly with date and author', function (array $row) {
    expect(fn () => priceListRawRow($row))->toThrow(QueryException::class);
})->with([
    // Closures: the rows are built when the test runs (dataset values are computed before the
    // application starts, so no factories or now() there).
    'unknown status' => [fn (): array => ['status' => 'archived']],
    'published without date' => [fn (): array => ['status' => 'published', 'published_by' => User::factory()->admin()->create()->id]],
    'published without author' => [fn (): array => ['status' => 'published', 'published_at' => now()]],
    'draft with a date' => [fn (): array => ['published_at' => now()]],
    'copy percentage without source' => [fn (): array => ['copy_increase_pct' => '5.00']],
]);

it('D-333: a published price list has its date and author', function () {
    $id = priceListRawRow(['status' => 'published', 'published_at' => now(), 'published_by' => User::factory()->admin()->create()->id]);

    expect(PriceList::query()->findOrFail($id)->isPublished())->toBeTrue();
});

it('C3: price lists, clubs, formats and authors referenced by a price list are never deleted', function () {
    $onDelete = fn (string $table): array => collect(Schema::getForeignKeys($table))
        ->mapWithKeys(fn (array $key): array => [$key['columns'][0] => strtolower((string) $key['on_delete'])])
        ->all();

    expect($onDelete('price_lists'))->toEqualCanonicalizing(['season_id' => 'restrict', 'published_by' => 'restrict', 'copied_from_id' => 'restrict'])
        ->and($onDelete('led_prices'))->toEqualCanonicalizing(['price_list_id' => 'restrict', 'club_id' => 'restrict', 'led_format_id' => 'restrict']);

    $priceList = PriceList::factory()->create();
    $priceId = ledPriceRawRow($priceList->id);
    $price = DB::table('led_prices')->where('id', $priceId)->first();

    expect(fn () => DB::table('price_lists')->where('id', $priceList->id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('clubs')->where('id', $price->club_id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('led_formats')->where('id', $price->led_format_id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('seasons')->where('id', $priceList->season_id)->delete())->toThrow(QueryException::class);
});
