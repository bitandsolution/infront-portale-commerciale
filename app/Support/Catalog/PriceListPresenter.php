<?php

namespace App\Support\Catalog;

use App\Domain\Catalog\Services\PriceListGrid;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\Competition;
use App\Models\LedFormat;
use App\Models\PriceList;
use App\Models\Season;
use App\Support\Amounts;
use App\Support\Sports\SportsPresenter;

/**
 * Data of the page "Catalogo › Listino LED" (M03 F-05, F-11; D-333, D-337) for the frontend: the
 * price list of the season with its status, the formats as columns, the clubs of the grid as rows
 * with their prices (decimal string and Italian notation), the competitions of the filter and,
 * before the publication, the clubs without offers.
 */
final class PriceListPresenter
{
    public function __construct(private readonly PriceListGrid $grid) {}

    /**
     * @return array<string, mixed>
     */
    public function ledPage(Season $season, ?PriceList $priceList, bool $canManage): array
    {
        $clubs = $this->grid->clubs($season);
        $formats = $this->grid->ledFormats();
        $prices = $priceList === null ? [] : self::ledPrices($priceList);

        return [
            'season' => ['id' => $season->id, 'code' => $season->code, 'readOnly' => $season->isReadOnly()],
            'canManage' => $canManage && ! $season->isReadOnly(),
            'priceList' => $priceList === null ? null : self::priceList($priceList),
            'createUrl' => route('catalog.price-lists.store'),
            'competitions' => self::competitions($clubs),
            'formats' => $formats
                ->map(fn (LedFormat $format): array => ['id' => $format->id, 'code' => $format->code, 'name' => $format->name])
                ->values()
                ->all(),
            'rows' => $clubs
                ->map(fn (Club $club): array => [
                    'clubId' => $club->id,
                    'name' => $club->name,
                    'competitionIds' => $club->enrollments
                        ->map(fn (ClubEnrollment $enrollment): int => $enrollment->competitionSeason->competition_id)
                        ->unique()->values()->all(),
                    'prices' => $formats
                        ->mapWithKeys(function (LedFormat $format) use ($prices, $club): array {
                            $price = $prices[$club->id][$format->id] ?? null;

                            return [(string) $format->id => $price === null ? null : [
                                'value' => $price,
                                'display' => Amounts::italian($price),
                            ]];
                        })
                        ->all(),
                ])
                ->values()
                ->all(),
            'withoutOffers' => $priceList !== null && ! $priceList->isPublished()
                ? $this->grid->clubsWithoutOffers($priceList)->pluck('name')->values()->all()
                : [],
        ];
    }

    /**
     * @return array{id: int, status: string, statusLabel: string, isPublished: bool, publishedAt: string|null, publishedBy: string|null, updateUrl: string, publishUrl: string}
     */
    public static function priceList(PriceList $priceList): array
    {
        return [
            'id' => $priceList->id,
            'status' => $priceList->status->value,
            'statusLabel' => $priceList->status->label(),
            'isPublished' => $priceList->isPublished(),
            'publishedAt' => SportsPresenter::dateTime($priceList->published_at),
            'publishedBy' => $priceList->publisher?->fullName(),
            'updateUrl' => route('catalog.led-prices.update', $priceList),
            'publishUrl' => route('catalog.price-lists.publish', $priceList),
        ];
    }

    /**
     * Club id → format id → cost for 1′ per match (decimal string).
     *
     * @return array<int, array<int, string>>
     */
    private static function ledPrices(PriceList $priceList): array
    {
        $prices = [];

        foreach ($priceList->ledPrices()->get() as $price) {
            $prices[$price->club_id][$price->led_format_id] = $price->unit_price;
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
