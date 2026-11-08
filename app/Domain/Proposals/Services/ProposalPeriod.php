<?php

namespace App\Domain\Proposals\Services;

use App\Domain\Sports\Services\CurrentSeason;
use App\Enums\SeasonStatus;
use App\Models\Season;
use App\Support\Text;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Season and period of a proposal (M06 §4 step 2, §8, RB-01; D-032, D-039, D-087, D-375): one
 * period for the whole proposal, inside one season.
 *
 * - Seasons offered to the configurator: every season not closed (a closed season is read-only,
 *   D-317), the oldest first.
 * - Defaults of a new draft (D-375): the season of the top bar (when it is not closed, otherwise the
 *   first season still running or to come); start = start of the season, or today when the season has
 *   already started; end = end of the season (D-039).
 * - Validation (M06 §8): start required and inside the season; end required, not before the start
 *   and not after the end of the season. One implementation, used by SaveDraft.
 *
 * Dates are calendar days in Europe/Rome (C7), as "Y-m-d" strings.
 */
final class ProposalPeriod
{
    public function __construct(private readonly CurrentSeason $currentSeason) {}

    /**
     * @return Collection<int, Season>
     */
    public function seasons(): Collection
    {
        return Season::query()
            ->where('status', '!=', SeasonStatus::Closed->value)
            ->orderBy('starts_on')
            ->get();
    }

    public function defaultSeason(?Carbon $today = null): ?Season
    {
        $current = $this->currentSeason->get();

        if ($current !== null && $current->status !== SeasonStatus::Closed) {
            return $current;
        }

        $seasons = $this->seasons();
        $day = self::today($today);

        return $seasons->first(fn (Season $season): bool => $season->ends_on->toDateString() >= $day) ?? $seasons->first();
    }

    /**
     * Default start: the start of the season, or today when the season is already running (D-375).
     */
    public static function defaultStart(Season $season, ?Carbon $today = null): string
    {
        $day = self::today($today);
        $starts = $season->starts_on->toDateString();

        return $day > $starts && $day <= $season->ends_on->toDateString() ? $day : $starts;
    }

    /**
     * Default end: the end of the season (D-039).
     */
    public static function defaultEnd(Season $season): string
    {
        return $season->ends_on->toDateString();
    }

    /**
     * Errors of a period in a season (M06 §8), by field; empty when the period is valid.
     *
     * @return array<string, string>
     */
    public static function errors(Season $season, ?string $startsOn, ?string $endsOn): array
    {
        $first = $season->starts_on->toDateString();
        $last = $season->ends_on->toDateString();
        $range = ['from' => self::italian($first), 'to' => self::italian($last)];
        $errors = [];

        if ($startsOn === null) {
            $errors['starts_on'] = Text::get('proposals.validation.starts_on_required');
        } elseif ($startsOn < $first || $startsOn > $last) {
            $errors['starts_on'] = Text::get('proposals.validation.starts_on_outside', $range);
        }

        if ($endsOn === null) {
            $errors['ends_on'] = Text::get('proposals.validation.ends_on_required');
        } elseif ($endsOn > $last) {
            $errors['ends_on'] = Text::get('proposals.validation.ends_on_after_season', $range);
        } elseif ($startsOn !== null && $endsOn < $startsOn) {
            $errors['ends_on'] = Text::get('proposals.validation.ends_on_before_start');
        }

        return $errors;
    }

    public static function italian(string $date): string
    {
        return Carbon::parse($date)->format('d/m/Y');
    }

    private static function today(?Carbon $today): string
    {
        return ($today ?? Carbon::now(CurrentSeason::TIMEZONE))->toDateString();
    }
}
