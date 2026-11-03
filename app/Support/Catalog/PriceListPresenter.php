<?php

namespace App\Support\Catalog;

use App\Domain\Catalog\Services\PriceListGrid;
use App\Enums\PriceKind;
use App\Models\BenefitCategory;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\Competition;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\OnTopAsset;
use App\Models\PartnershipBenefit;
use App\Models\PartnershipTier;
use App\Models\PriceList;
use App\Models\Season;
use App\Support\Amounts;
use App\Support\Sports\SportsPresenter;

/**
 * Data of the price list pages for the frontend (M03 F-05, F-07…F-11; D-333, D-335, D-337, D-341):
 * the grids "Listini LED", "Partnership" and "Hospitality" (status of the price list, values of the
 * catalogue as columns, clubs as rows with their amounts as decimal string and Italian notation,
 * competitions of the filter, clubs without offers before the publication), and the page of the
 * benefit and on-top texts of a club.
 */
final class PriceListPresenter
{
    public function __construct(private readonly PriceListGrid $grid) {}

    /**
     * @return array<string, mixed>
     */
    public function gridPage(PriceKind $kind, Season $season, ?PriceList $priceList, bool $canManage): array
    {
        $clubs = $this->grid->clubs($season);
        $columns = $this->grid->columns($kind);
        $prices = $priceList === null ? [] : self::prices($priceList, $kind);
        $canWrite = $canManage && ! $season->isReadOnly();

        return [
            'kind' => $kind->value,
            'season' => ['id' => $season->id, 'code' => $season->code, 'readOnly' => $season->isReadOnly()],
            'canManage' => $canWrite,
            'priceList' => $priceList === null ? null : self::priceList($priceList, $kind),
            'createUrl' => route('catalog.price-lists.store'),
            'competitions' => self::competitions($clubs),
            'columns' => $columns
                ->map(fn (LedFormat|PartnershipTier|HospitalityVariant $value): array => ['id' => $value->id, 'code' => $value->code, 'name' => $value->name])
                ->values()
                ->all(),
            'rows' => $clubs
                ->map(fn (Club $club): array => [
                    'clubId' => $club->id,
                    'name' => $club->name,
                    'competitionIds' => $club->enrollments
                        ->map(fn (ClubEnrollment $enrollment): int => $enrollment->competitionSeason->competition_id)
                        ->unique()->values()->all(),
                    'prices' => $columns
                        ->mapWithKeys(function (LedFormat|PartnershipTier|HospitalityVariant $value) use ($prices, $club): array {
                            $price = $prices[$club->id][$value->id] ?? null;

                            return [(string) $value->id => $price === null ? null : [
                                'value' => $price,
                                'display' => Amounts::italian($price),
                            ]];
                        })
                        ->all(),
                    'benefitsUrl' => $kind === PriceKind::Partnership && $priceList !== null
                        ? route('catalog.benefits.show', [$priceList, $club])
                        : null,
                ])
                ->values()
                ->all(),
            'withoutOffers' => $priceList !== null && ! $priceList->isPublished()
                ? $this->grid->clubsWithoutOffers($priceList)->pluck('name')->values()->all()
                : [],
            'onTop' => $kind === PriceKind::Partnership && $priceList !== null
                ? self::onTop($priceList, null)
                : null,
        ];
    }

    /**
     * Benefit texts of a club, one per tier and category, and its on-top texts (D-335).
     *
     * @return array<string, mixed>
     */
    public function benefitsPage(PriceList $priceList, Club $club, bool $canManage): array
    {
        $benefits = $priceList->partnershipBenefits()->where('club_id', $club->id)->get();
        $categories = BenefitCategory::query()->orderBy('sort_order')->orderBy('name')->get();
        $season = $priceList->season;

        return [
            'season' => ['id' => $season->id, 'code' => $season->code, 'readOnly' => $season->isReadOnly()],
            'canManage' => $canManage && ! $season->isReadOnly(),
            'priceList' => self::priceList($priceList, PriceKind::Partnership),
            'club' => ['id' => $club->id, 'name' => $club->name],
            'tiers' => $this->grid->partnershipTiers()
                ->map(fn (PartnershipTier $tier): array => [
                    'id' => $tier->id,
                    'name' => $tier->name,
                    'saveUrl' => route('catalog.benefits.update', [$priceList, $club]),
                    'categories' => $categories
                        ->map(fn (BenefitCategory $category): array => [
                            'id' => $category->id,
                            'name' => $category->name,
                            'body' => $benefits
                                ->first(fn (PartnershipBenefit $benefit): bool => $benefit->partnership_tier_id === $tier->id && $benefit->benefit_category_id === $category->id)
                                ?->body ?? '',
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'onTop' => self::onTop($priceList, $club),
            'backUrl' => route('pages.catalog_partnership'),
        ];
    }

    /**
     * @return array{id: int, status: string, statusLabel: string, isPublished: bool, publishedAt: string|null, publishedBy: string|null, updateUrl: string, publishUrl: string}
     */
    public static function priceList(PriceList $priceList, PriceKind $kind): array
    {
        return [
            'id' => $priceList->id,
            'status' => $priceList->status->value,
            'statusLabel' => $priceList->status->label(),
            'isPublished' => $priceList->isPublished(),
            'publishedAt' => SportsPresenter::dateTime($priceList->published_at),
            'publishedBy' => $priceList->publisher?->fullName(),
            'updateUrl' => route('catalog.prices.update', [$priceList, $kind->value]),
            'publishUrl' => route('catalog.price-lists.publish', $priceList),
        ];
    }

    /**
     * On-top texts of a club, or the general ones (club null), in their order.
     *
     * @return array{items: list<array{id: int, title: string, body: string, updateUrl: string, removeUrl: string}>, createUrl: string, clubId: int|null}
     */
    private static function onTop(PriceList $priceList, ?Club $club): array
    {
        return [
            'items' => $priceList->onTopAssets()
                ->where('club_id', $club?->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (OnTopAsset $asset): array => [
                    'id' => $asset->id,
                    'title' => $asset->title,
                    'body' => $asset->body,
                    'updateUrl' => route('catalog.on-top.update', $asset),
                    'removeUrl' => route('catalog.on-top.destroy', $asset),
                ])
                ->values()
                ->all(),
            'createUrl' => route('catalog.on-top.store', $priceList),
            'clubId' => $club?->id,
        ];
    }

    /**
     * Club id → column id → amount (decimal string).
     *
     * @return array<int, array<int, string>>
     */
    private static function prices(PriceList $priceList, PriceKind $kind): array
    {
        $model = $kind->priceModel();
        $prices = [];

        foreach ($model::query()->where('price_list_id', $priceList->id)->get() as $price) {
            /** @var int $column */
            $column = $price->getAttribute($kind->columnKey());
            /** @var string $amount */
            $amount = $price->getAttribute($kind->amountColumn());
            $prices[$price->club_id][$column] = $amount;
        }

        return $prices;
    }

    /**
     * Competitions of the clubs of the grid, in their order, for the filter (as in the mockup).
     *
     * @param  iterable<Club>  $clubs
     * @return list<array{id: int, name: string}>
     */
    private static function competitions(iterable $clubs): array
    {
        $competitions = [];

        foreach ($clubs as $club) {
            foreach ($club->enrollments as $enrollment) {
                $competition = $enrollment->competitionSeason->competition;
                $competitions[$competition->id] = $competition;
            }
        }

        usort($competitions, fn (Competition $a, Competition $b): int => [$a->sort_order, $a->name] <=> [$b->sort_order, $b->name]);

        return array_map(fn (Competition $competition): array => ['id' => $competition->id, 'name' => $competition->name], $competitions);
    }
}
