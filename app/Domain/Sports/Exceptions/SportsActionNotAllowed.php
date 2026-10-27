<?php

namespace App\Domain\Sports\Exceptions;

use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * A rule of the sports registry refuses the operation (M02 §8; D-317). Nothing has been written.
 * In the portal the user goes back to the page with the reason in a toast (ux-architettura §4.5).
 */
final class SportsActionNotAllowed extends RuntimeException
{
    /** M02 §8: seasons of the same discipline never overlap. */
    public static function seasonOverlaps(string $otherCode): self
    {
        return new self(Text::get('sports.errors.season_overlaps', ['season' => $otherCode]));
    }

    /** D-317: the sports registry of a closed season is read-only. */
    public static function seasonClosed(string $code): self
    {
        return new self(Text::get('sports.errors.season_closed', ['season' => $code]));
    }

    public static function editionExists(string $label): self
    {
        return new self(Text::get('sports.errors.edition_exists', ['edition' => $label]));
    }

    /** An edition with enrollments, matches or imports is never removed (C4, FK RESTRICT). */
    public static function editionInUse(string $label): self
    {
        return new self(Text::get('sports.errors.edition_in_use', ['edition' => $label]));
    }

    /** D-185: an alias never points to two clubs, nor repeats the name of another club. */
    public static function aliasTaken(string $alias, string $club): self
    {
        return new self(Text::get('sports.errors.alias_taken', ['alias' => $alias, 'club' => $club]));
    }

    public static function aliasEmpty(): self
    {
        return new self(Text::get('sports.errors.alias_empty'));
    }

    /** F-06, D-185: one league per club and season. */
    public static function clubInAnotherLeague(string $club, string $competition): self
    {
        return new self(Text::get('sports.errors.club_in_another_league', ['club' => $club, 'competition' => $competition]));
    }

    public static function clubAlreadyEnrolled(string $club): self
    {
        return new self(Text::get('sports.errors.club_already_enrolled', ['club' => $club]));
    }

    /** An enrollment with matches of the club in that edition stays (the calendar needs it). */
    public static function enrollmentHasMatches(string $club): self
    {
        return new self(Text::get('sports.errors.enrollment_has_matches', ['club' => $club]));
    }

    /** M02 §8: both teams enrolled in the competition in that season. */
    public static function clubNotEnrolled(string $club, string $edition): self
    {
        return new self(Text::get('sports.errors.club_not_enrolled', ['club' => $club, 'edition' => $edition]));
    }

    /** M02 §8: the date falls within the season. */
    public static function dateOutsideSeason(string $season): self
    {
        return new self(Text::get('sports.errors.date_outside_season', ['season' => $season]));
    }

    /** M02 §8, F-10: no two matches with the same matchday, home and away clubs. */
    public static function matchExists(): self
    {
        return new self(Text::get('sports.errors.match_exists'));
    }

    /** F-09: the cells of the calendar file cannot be read (damaged or not a worksheet). */
    public static function calendarUnreadable(): self
    {
        return new self(Text::get('sports.errors.calendar_unreadable'));
    }

    /** F-09: no header row or no match rows in the first sheet. */
    public static function calendarEmpty(): self
    {
        return new self(Text::get('sports.errors.calendar_empty'));
    }

    /** D-322: a calendar has about 380–2,000 rows. */
    public static function calendarTooManyRows(int $max): self
    {
        return new self(Text::get('sports.errors.calendar_too_many_rows', ['max' => (string) $max]));
    }

    /** FL-02: a confirmed or discarded import is read-only. */
    public static function importClosed(): self
    {
        return new self(Text::get('sports.errors.import_closed'));
    }

    /** FL-02 step 4: nothing valid to import (all rows discarded, or columns still to choose). */
    public static function importNothingToConfirm(): self
    {
        return new self(Text::get('sports.errors.import_nothing_to_confirm'));
    }

    /** D-325: every required field needs its own column of the file. */
    public static function importColumnsInvalid(): self
    {
        return new self(Text::get('sports.errors.import_columns_invalid'));
    }

    /** FL-02 step 3: only the unknown names of the preview are associated. */
    public static function importNameNotUnknown(string $name): self
    {
        return new self(Text::get('sports.errors.import_name_not_unknown', ['name' => $name]));
    }

    public function render(): RedirectResponse
    {
        return back()->with('toast', Toast::error($this->getMessage()));
    }

    /** D-328: the match service needs the league of every club of the contract. */
    public static function clubNotInLeague(string $club, string $season): self
    {
        return new self(Text::get('sports.errors.club_not_in_league', ['club' => $club, 'season' => $season]));
    }

    /** D-320: the period of the contract falls within the season. */
    public static function periodOutsideSeason(string $season): self
    {
        return new self(Text::get('sports.errors.period_outside_season', ['season' => $season]));
    }
}
