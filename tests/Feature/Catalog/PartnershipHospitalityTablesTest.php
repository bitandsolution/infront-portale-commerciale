<?php

use App\Models\BenefitCategory;
use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\PartnershipTier;
use App\Models\PriceList;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Tables of partnership and hospitality prices, benefits and on-top texts (D-190, D-335,
| progettazione-tecnica §5.3.3; C3, C5, C9; F2-03): rows written directly in SQL, as a SQL client
| would, so the constraints hold for every writer.
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function catalogTextRow(string $table, array $overrides = []): int
{
    $base = ['price_list_id' => PriceList::factory()->create()->id, 'club_id' => Club::factory()->infront()->create()->id, 'created_at' => now(), 'updated_at' => now()];

    $row = match ($table) {
        'partnership_prices' => ['partnership_tier_id' => PartnershipTier::factory()->create()->id, 'season_price' => '135000.00'],
        'hospitality_prices' => ['hospitality_variant_id' => HospitalityVariant::factory()->create()->id, 'seat_price' => '4500.00'],
        'partnership_benefits' => ['partnership_tier_id' => PartnershipTier::factory()->create()->id, 'benefit_category_id' => BenefitCategory::factory()->create()->id, 'body' => 'Testo'],
        default => ['title' => 'Match sponsor', 'body' => 'Testo'],
    };

    return DB::table($table)->insertGetId(array_merge($base, $row, $overrides));
}

it('D-190, D-335: creates the four tables with the columns of the data model', function () {
    expect(Schema::getColumnListing('partnership_prices'))->toEqualCanonicalizing(['id', 'price_list_id', 'club_id', 'partnership_tier_id', 'season_price', 'created_at', 'updated_at'])
        ->and(Schema::getColumnListing('hospitality_prices'))->toEqualCanonicalizing(['id', 'price_list_id', 'club_id', 'hospitality_variant_id', 'seat_price', 'created_at', 'updated_at'])
        ->and(Schema::getColumnListing('partnership_benefits'))->toEqualCanonicalizing(['id', 'price_list_id', 'club_id', 'partnership_tier_id', 'benefit_category_id', 'body', 'sort_order', 'created_at', 'updated_at'])
        ->and(Schema::getColumnListing('on_top_assets'))->toEqualCanonicalizing(['id', 'price_list_id', 'club_id', 'title', 'body', 'sort_order', 'created_at', 'updated_at']);
});

it('D-190, D-335: one price per club and value, one benefit per club, tier and category', function (string $table) {
    $id = catalogTextRow($table);
    $row = (array) DB::table($table)->where('id', $id)->first();
    unset($row['id']);

    expect(fn () => DB::table($table)->insert($row))->toThrow(QueryException::class);
})->with(['partnership_prices', 'hospitality_prices', 'partnership_benefits']);

it('C5, C9: prices are never negative, texts and titles never empty', function (string $table, array $row) {
    expect(fn () => catalogTextRow($table, $row))->toThrow(QueryException::class);
})->with([
    'negative partnership price' => ['partnership_prices', ['season_price' => '-1.00']],
    'negative seat price' => ['hospitality_prices', ['seat_price' => '-0.01']],
    'empty benefit' => ['partnership_benefits', ['body' => '  ']],
    'empty on-top title' => ['on_top_assets', ['title' => ' ']],
    'empty on-top text' => ['on_top_assets', ['body' => '']],
]);

it('D-049: an on-top text can be general, without a club', function () {
    $id = catalogTextRow('on_top_assets', ['club_id' => null]);

    expect(DB::table('on_top_assets')->where('id', $id)->value('club_id'))->toBeNull();
});

it('C3: every key of the four tables is restrictive', function () {
    $onDelete = fn (string $table): array => collect(Schema::getForeignKeys($table))
        ->mapWithKeys(fn (array $key): array => [$key['columns'][0] => strtolower((string) $key['on_delete'])])
        ->all();

    expect($onDelete('partnership_prices'))->toEqualCanonicalizing(['price_list_id' => 'restrict', 'club_id' => 'restrict', 'partnership_tier_id' => 'restrict'])
        ->and($onDelete('hospitality_prices'))->toEqualCanonicalizing(['price_list_id' => 'restrict', 'club_id' => 'restrict', 'hospitality_variant_id' => 'restrict'])
        ->and($onDelete('partnership_benefits'))->toEqualCanonicalizing(['price_list_id' => 'restrict', 'club_id' => 'restrict', 'partnership_tier_id' => 'restrict', 'benefit_category_id' => 'restrict'])
        ->and($onDelete('on_top_assets'))->toEqualCanonicalizing(['price_list_id' => 'restrict', 'club_id' => 'restrict']);

    $id = catalogTextRow('partnership_benefits');
    $benefit = DB::table('partnership_benefits')->where('id', $id)->first();

    expect(fn () => DB::table('benefit_categories')->where('id', $benefit->benefit_category_id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('partnership_tiers')->where('id', $benefit->partnership_tier_id)->delete())->toThrow(QueryException::class);
});
