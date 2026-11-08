<?php

namespace App\Domain\Proposals\Services;

use App\Domain\Catalog\Data\ClubSelection;
use App\Domain\Catalog\Exceptions\CatalogActionNotAllowed;
use App\Domain\Catalog\Services\PriceCalculator;
use App\Domain\Inventory\Data\ClubSellability;
use App\Domain\Inventory\Data\SellableOption;
use App\Domain\Inventory\Services\SellableOptions;
use App\Domain\Proposals\Data\CampaignKpis;
use App\Domain\Proposals\Data\ClubEvaluation;
use App\Domain\Proposals\Data\DraftClubData;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Settings\Services\Settings;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Domain\Sports\Services\MatchCounter;
use App\Enums\SellableStatus;
use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Season;
use App\Support\LedDuration;
use App\Support\Text;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;

/**
 * Evaluation of the clubs of a draft (M06 §5, §7, §8, §9; RB-02…RB-10; M03-CA8, M04-CA5, M04-CA7;
 * D-051…D-055, D-063…D-066, D-088, D-147, D-328, D-373; F4-03). The only implementation, used by
 * SaveDraft at every save and by the configurator page; F5 will use it again at the submission
 * (RB-11). It never stops at the first error: every club is evaluated and every error is collected,
 * club by club and field by field.
 *
 * - What can be sold comes from SellableOptions (published list, price, managed resources, residual);
 *   a chosen tier, format or variant that is not available any more (sold out, not managed, no price)
 *   is an error of its field (M06 §9).
 * - Minutes: steps of the settings (D-063), at most the residual of the format (RB-10, D-064); seats:
 *   at most the residual of the variant and at most the maximum of the settings per club (D-147).
 * - A club with the LED and no home match in the period is an error (M06 §8); a club not enrolled in
 *   a league of the season cannot be counted (D-328).
 * - A club without any element (a tier other than "Libera", LED or hospitality) is an error (RB-08):
 *   the draft is saved anyway, the error blocks the summary and, with F5, the submission (D-373).
 * - Prices from PriceCalculator, one club at a time so that an error of a club does not stop the
 *   others; the options with a price in the published list are priced also when sold out or beyond
 *   the residual, so the agent sees what they would cost. Without a price the club price is not
 *   calculated. The list total is the sum of the club prices (RB-07).
 * - KPI of the campaign (M06 §6, D-093; F4-04): MatchCounter on all the priced clubs together, so the
 *   campaign starts with the first home match of any of them (RB-02) and the top matches count only
 *   in the competitions with the KPI (D-036); spots from the LED of the priced clubs.
 */
final class DraftEvaluator
{
    public function __construct(
        private readonly SellableOptions $sellable,
        private readonly PriceCalculator $calculator,
        private readonly Settings $settings,
        private readonly MatchCounter $matchCounter,
    ) {}

    /**
     * @param  list<DraftClubData>  $clubs
     */
    public function evaluate(?Season $season, ?string $startsOn, ?string $endsOn, array $clubs): DraftEvaluation
    {
        $models = Club::query()->whereKey(array_map(fn (DraftClubData $club): int => $club->clubId, $clubs))->get()->keyBy('id');
        $evaluations = [];
        $total = BigDecimal::zero();

        foreach ($clubs as $data) {
            $club = $models->get($data->clubId);

            if (! $club instanceof Club) {
                continue;
            }

            $evaluation = $season === null || $startsOn === null
                ? new ClubEvaluation($data, $club, null, null, ['club' => Text::get('proposals.club_errors.no_season')])
                : $this->club($season, Carbon::parse($startsOn), $endsOn !== null ? Carbon::parse($endsOn) : null, $club, $data);

            $evaluations[] = $evaluation;
            $total = $total->plus($evaluation->total());
        }

        return new DraftEvaluation(
            $evaluations,
            (string) $total->toScale(2),
            $season !== null && $startsOn !== null ? $this->campaign($season, $startsOn, $endsOn, $evaluations) : null,
        );
    }

    /**
     * KPI of the campaign on the clubs that can be priced (the others have an error and are not
     * counted); null without any of them.
     *
     * @param  list<ClubEvaluation>  $evaluations
     */
    private function campaign(Season $season, string $startsOn, ?string $endsOn, array $evaluations): ?CampaignKpis
    {
        $priced = array_values(array_filter($evaluations, fn (ClubEvaluation $club): bool => $club->quote !== null));

        if ($priced === []) {
            return null;
        }

        try {
            $count = $this->matchCounter->count(
                $season,
                array_map(fn (ClubEvaluation $club): Club => $club->club, $priced),
                Carbon::parse($startsOn),
                $endsOn !== null ? Carbon::parse($endsOn) : null,
            );
        } catch (SportsActionNotAllowed) {
            return null;
        }

        $ledMatches = 0;
        $spots = 0;

        foreach ($priced as $club) {
            $quote = $club->quote;

            if ($quote !== null && $quote->ledFormat !== null) {
                $ledMatches += $quote->matches->matches;
                $spots += CampaignKpis::spotsPerMatch($quote->ledSeconds) * $quote->matches->matches;
            }
        }

        return new CampaignKpis(
            clubs: count($evaluations),
            totalMatches: $count->totalMatches,
            ledMatches: $ledMatches,
            totalSpots: $spots,
            campaignStart: $count->campaignStart,
            campaignEnd: $count->campaignEnd,
            campaignMonths: $count->campaignMonths,
            topMatches: $count->topMatches,
            topMatchesStatus: $count->topMatchesStatus,
            fromCalendar: $count->fromCalendar(),
        );
    }

    private function club(Season $season, Carbon $startsOn, ?Carbon $endsOn, Club $club, DraftClubData $data): ClubEvaluation
    {
        $sellability = $this->sellable->forClub($season, $club);

        if (! $sellability->isSelectable()) {
            return new ClubEvaluation($data, $club, $sellability, null, ['club' => Text::get('proposals.club_errors.not_selectable', [
                'club' => $club->name,
                'season' => $season->code,
                'reason' => $sellability->reason?->label() ?? '',
            ])]);
        }

        $errors = [];
        $tier = $this->tier($sellability, $data, $errors);
        $format = $this->format($sellability, $data, $errors);
        $seats = $this->seats($sellability, $data, $errors);

        $quote = null;

        try {
            $quote = $this->calculator->quote($season, [new ClubSelection(
                club: $club,
                ledFormat: $format,
                ledSeconds: $format !== null ? $data->ledSeconds : 0,
                tier: $tier,
                seats: $seats,
            )], $startsOn, $endsOn)->clubs[0];
        } catch (SportsActionNotAllowed|CatalogActionNotAllowed $exception) {
            $errors = ['club' => Text::get('proposals.club_errors.not_priced', ['reason' => $exception->getMessage()]), ...$errors];
        }

        if ($quote !== null && $data->ledFormatId !== null && $quote->matches->matches === 0) {
            $errors['matches'] = Text::get('proposals.club_errors.no_matches');
        }

        if (! $this->hasElements($sellability, $data)) {
            $errors['elements'] = Text::get('proposals.club_errors.no_elements');
        }

        return new ClubEvaluation($data, $club, $sellability, $quote, $errors);
    }

    /**
     * The chosen package: "Libera" or a tier with its residual (D-052, D-071). Returns the tier to
     * price when the list has a price for it.
     *
     * @param  array<string, string>  $errors
     */
    private function tier(ClubSellability $sellability, DraftClubData $data, array &$errors): ?PartnershipTier
    {
        if ($data->tierId === null) {
            return null;
        }

        $option = self::find($sellability->partnership, $data->tierId);

        if ($option === null || ! $option->status->isSelectable()) {
            $errors['tier'] = self::unavailable('tier', $option, PartnershipTier::query()->find($data->tierId)?->name);
        }

        if ($option === null || (! $option->isFreePackage && $option->listPrice === null)) {
            return null;
        }

        return PartnershipTier::query()->find($data->tierId);
    }

    /**
     * The LED format (one per club, D-088) and its minutes: steps of the settings, at most the
     * residual of the format (D-063, D-064, D-065).
     *
     * @param  array<string, string>  $errors
     */
    private function format(ClubSellability $sellability, DraftClubData $data, array &$errors): ?LedFormat
    {
        if ($data->ledFormatId === null) {
            return null;
        }

        $option = self::find($sellability->led, $data->ledFormatId);

        if ($option === null || ! $option->status->isSelectable()) {
            $errors['led_format'] = self::unavailable('format', $option, LedFormat::query()->find($data->ledFormatId)?->name);
        }

        $step = $this->settings->ledStepSeconds();

        if ($data->ledSeconds <= 0 || $data->ledSeconds % $step !== 0) {
            $errors['led_seconds'] = Text::get('proposals.club_errors.led_step', ['step' => LedDuration::format($step)]);
        } elseif ($option !== null && $option->status->isSelectable() && $option->remaining !== null && $data->ledSeconds > $option->remaining) {
            $errors['led_seconds'] = Text::get('proposals.club_errors.led_over_residual', [
                'step' => LedDuration::format($step),
                'remaining' => LedDuration::format($option->remaining),
            ]);
        }

        // Priced also beyond the residual; never with minutes out of step (PriceCalculator refuses them).
        if ($option === null || $option->listPrice === null || $data->ledSeconds <= 0 || $data->ledSeconds % $step !== 0) {
            return null;
        }

        return LedFormat::query()->find($data->ledFormatId);
    }

    /**
     * Hospitality seats by variant: always selectable, also without a partnership (D-055); at most the
     * residual of the variant and at most the maximum of the settings per club (D-147).
     *
     * @param  array<string, string>  $errors
     * @return list<array{variant: HospitalityVariant, seats: int}>
     */
    private function seats(ClubSellability $sellability, DraftClubData $data, array &$errors): array
    {
        $seats = [];
        $max = $this->settings->hospitalityMaxSeats();

        foreach ($data->seats as $variantId => $count) {
            if ($count <= 0) {
                continue;
            }

            $option = self::find($sellability->hospitality, $variantId);
            $field = 'seats.'.$variantId;

            if ($option === null || ! $option->status->isSelectable()) {
                $errors[$field] = self::unavailable('variant', $option, HospitalityVariant::query()->find($variantId)?->name);
            } elseif ($option->remaining !== null && $count > $option->remaining) {
                $errors[$field] = Text::get('proposals.club_errors.seats_over_residual', [
                    'variant' => $option->name,
                    'remaining' => (string) $option->remaining,
                ]);
            }

            $variant = $option !== null && $option->listPrice !== null ? HospitalityVariant::query()->find($variantId) : null;

            if ($variant !== null) {
                $seats[] = ['variant' => $variant, 'seats' => $count];
            }
        }

        if ($data->totalSeats() > $max) {
            $errors['seats'] = Text::get('proposals.club_errors.seats_max', ['max' => (string) $max]);

            // PriceCalculator refuses more seats than the maximum: the club is priced without them.
            return [];
        }

        return $seats;
    }

    /**
     * RB-08, D-373: a partnership other than "Libera", the LED or at least one hospitality seat.
     */
    private function hasElements(ClubSellability $sellability, DraftClubData $data): bool
    {
        if ($data->ledFormatId !== null || $data->totalSeats() > 0) {
            return true;
        }

        if ($data->tierId === null) {
            return false;
        }

        $option = self::find($sellability->partnership, $data->tierId);

        return $option === null || ! $option->isFreePackage;
    }

    /**
     * @param  list<SellableOption>  $options
     */
    private static function find(array $options, int $valueId): ?SellableOption
    {
        foreach ($options as $option) {
            if ($option->valueId === $valueId) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Message of a chosen option that cannot be sold: sold out (visible, not selectable) or no longer
     * on sale (not managed, no price, inactive value).
     */
    private static function unavailable(string $kind, ?SellableOption $option, ?string $name): string
    {
        $key = $option?->status === SellableStatus::SoldOut ? 'sold_out' : 'not_available';

        return Text::get("proposals.club_errors.{$kind}_{$key}", ['name' => $option !== null ? $option->name : ($name ?? '')]);
    }
}
