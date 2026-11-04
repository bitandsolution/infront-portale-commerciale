<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Data\ClubQuote;
use App\Domain\Catalog\Data\ClubSelection;
use App\Domain\Catalog\Data\HospitalityLine;
use App\Domain\Catalog\Data\Quote;
use App\Domain\Catalog\Data\SeasonalLedPrice;
use App\Domain\Catalog\Exceptions\CatalogActionNotAllowed;
use App\Domain\Settings\Services\Settings;
use App\Domain\Sports\Data\ClubMatchCount;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Domain\Sports\Services\MatchCounter;
use App\Enums\PriceKind;
use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\PriceList;
use App\Models\Season;
use App\Support\LedDuration;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * List prices of the portal (M03 RB-01…RB-05, RB-09; M03-CA1, CA2, CA3, CA9; D-029, D-054, D-055,
 * D-057, D-190, D-336, D-340; F2-06): the only implementation, used by the configurator (F4), the
 * approval (F5), the documents (F7), the command catalog:price and the indicative seasonal price of
 * the page "Listini LED" (CLAUDE.md §4). Exact decimals with brick/math (C5), never floats.
 *
 * - LED = cost for 1′ per match × seconds / 60 × home league matches of the period, from
 *   MatchCounter (D-054); a single rounding to the cent, 0.5 up, on the price of the club (D-190,
 *   D-336).
 * - Partnership = seasonal fixed price, also when the season has started (D-029); the free package
 *   "Libera" costs nothing (D-052).
 * - Hospitality = seats × price per seat (D-055), also without a partnership; at most the seats of
 *   the settings per club (D-147).
 * - A proposal uses only the published price list of its season (M03 RB-09) and only the options
 *   with a price for the club (M03-CA4); the residuals are checked by SellableOptions, not here.
 */
final class PriceCalculator
{
    public function __construct(
        private readonly MatchCounter $matchCounter,
        private readonly Settings $settings,
    ) {}

    /**
     * LED price of a club: cost for 1′ per match × seconds / 60 × matches, rounded once to the cent.
     */
    public function ledPrice(string $unitPrice, int $secondsPerMatch, int $matches): string
    {
        return (string) BigDecimal::of($unitPrice)
            ->multipliedBy($secondsPerMatch)
            ->multipliedBy($matches)
            ->dividedBy(60, 2, RoundingMode::HalfUp);
    }

    public function hospitalityPrice(string $seatPrice, int $seats): string
    {
        return (string) BigDecimal::of($seatPrice)->multipliedBy($seats)->toScale(2);
    }

    /**
     * Indicative price of 1′ per match for the whole season of the club (D-340), or null when the
     * club is not enrolled in a league of the season (D-328).
     */
    public function seasonalLedPrice(Season $season, Club $club, string $unitPrice): ?SeasonalLedPrice
    {
        $matches = $this->seasonMatches($season, $club);

        return $matches === null ? null : $this->seasonal($matches, $unitPrice);
    }

    /**
     * Home league matches of the whole season of the club, null without a league edition (D-328).
     */
    public function seasonMatches(Season $season, Club $club): ?ClubMatchCount
    {
        try {
            return $this->matchCounter->count($season, [$club], $season->starts_on)->clubs[0];
        } catch (SportsActionNotAllowed) {
            return null;
        }
    }

    public function seasonal(ClubMatchCount $matches, string $unitPrice): SeasonalLedPrice
    {
        return new SeasonalLedPrice($this->ledPrice($unitPrice, 60, $matches->matches), $matches->matches, $matches->fromCalendar);
    }

    /**
     * List prices of a proposal on one or more clubs, for the contract from $startsOn to $endsOn
     * (the end of the season by default, D-039).
     *
     * @param  list<ClubSelection>  $selections
     *
     * @throws CatalogActionNotAllowed when the list is not published, an option has no price for
     *                                 the club or the minutes or seats are not valid
     * @throws SportsActionNotAllowed when the period or the enrollments do not allow the count (D-328)
     */
    public function quote(Season $season, array $selections, Carbon $startsOn, ?Carbon $endsOn = null): Quote
    {
        $priceList = PriceList::query()->where('season_id', $season->id)->first();

        if ($priceList === null || ! $priceList->isPublished()) {
            throw CatalogActionNotAllowed::priceListNotPublished($season->code);
        }

        $clubIds = array_map(fn (ClubSelection $selection): int => $selection->club->id, $selections);

        if ($selections === [] || count(array_unique($clubIds)) !== count($clubIds)) {
            throw CatalogActionNotAllowed::invalidSelection();
        }

        foreach ($selections as $selection) {
            $this->validate($selection);
        }

        $matchCount = $this->matchCounter->count($season, array_map(fn (ClubSelection $selection): Club => $selection->club, $selections), $startsOn, $endsOn);
        $quotes = [];
        $total = BigDecimal::zero();

        foreach ($selections as $index => $selection) {
            $quote = $this->clubQuote($priceList, $selection, $matchCount->clubs[$index]);
            $quotes[] = $quote;
            $total = $total->plus($quote->total);
        }

        return new Quote($priceList, $matchCount, $quotes, (string) $total->toScale(2), $matchCount->warnings);
    }

    private function clubQuote(PriceList $priceList, ClubSelection $selection, ClubMatchCount $matches): ClubQuote
    {
        $club = $selection->club;
        $unitPrice = null;
        $ledPrice = '0.00';

        if ($selection->ledFormat !== null) {
            $unitPrice = $this->price($priceList, $club, PriceKind::Led, $selection->ledFormat);
            $ledPrice = $this->ledPrice($unitPrice, $selection->ledSeconds, $matches->matches);
        }

        $partnershipPrice = $selection->tier === null || $selection->tier->is_free_package
            ? '0.00'
            : $this->price($priceList, $club, PriceKind::Partnership, $selection->tier);

        $lines = [];
        $hospitality = BigDecimal::zero();

        foreach ($selection->seats as $item) {
            $seatPrice = $this->price($priceList, $club, PriceKind::Hospitality, $item['variant']);
            $line = new HospitalityLine($item['variant'], $item['seats'], $seatPrice, $this->hospitalityPrice($seatPrice, $item['seats']));
            $lines[] = $line;
            $hospitality = $hospitality->plus($line->price);
        }

        $hospitalityPrice = (string) $hospitality->toScale(2);

        return new ClubQuote(
            club: $club,
            matches: $matches,
            ledFormat: $selection->ledFormat,
            ledSeconds: $selection->ledFormat === null ? 0 : $selection->ledSeconds,
            ledUnitPrice: $unitPrice,
            ledPrice: $ledPrice,
            tier: $selection->tier,
            partnershipPrice: $partnershipPrice,
            hospitality: $lines,
            hospitalityPrice: $hospitalityPrice,
            total: (string) BigDecimal::of($ledPrice)->plus($partnershipPrice)->plus($hospitalityPrice)->toScale(2),
        );
    }

    /**
     * Minutes in steps of the settings (D-063), one format per club (D-088), seats from 1 and at
     * most the maximum of the settings per club (D-147), one line per variant.
     */
    private function validate(ClubSelection $selection): void
    {
        $club = $selection->club->name;
        $step = $this->settings->ledStepSeconds();

        if ($selection->ledFormat !== null && ($selection->ledSeconds <= 0 || $selection->ledSeconds % $step !== 0)) {
            throw CatalogActionNotAllowed::ledStep($club, LedDuration::format($step));
        }

        if ($selection->ledFormat === null && $selection->ledSeconds !== 0) {
            throw CatalogActionNotAllowed::invalidSelection();
        }

        $variants = [];
        $seats = 0;

        foreach ($selection->seats as $item) {
            if ($item['seats'] < 1 || in_array($item['variant']->id, $variants, true)) {
                throw CatalogActionNotAllowed::invalidSelection();
            }

            $variants[] = $item['variant']->id;
            $seats += $item['seats'];
        }

        $max = $this->settings->hospitalityMaxSeats();

        if ($seats > $max) {
            throw CatalogActionNotAllowed::tooManySeats($club, $max);
        }
    }

    /**
     * Price of the club for a value in the list; a missing row is "not available" (D-190).
     */
    private function price(PriceList $priceList, Club $club, PriceKind $kind, LedFormat|PartnershipTier|HospitalityVariant $value): string
    {
        $model = $kind->priceModel();
        $amount = $model::query()
            ->where('price_list_id', $priceList->id)
            ->where('club_id', $club->id)
            ->where($kind->columnKey(), $value->id)
            ->value($kind->amountColumn());

        if (! is_string($amount) && ! is_numeric($amount)) {
            throw CatalogActionNotAllowed::priceNotAvailable($value->name, $club->name, $priceList->season->code);
        }

        return (string) BigDecimal::of((string) $amount)->toScale(2);
    }
}
