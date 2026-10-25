<?php

namespace App\Domain\Sports\Services;

use App\Models\Season;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;

/**
 * Season selected in the top bar (D-317), resolved once per request (scoped binding).
 *
 * - The choice of the user stays in his session.
 * - Without a valid choice: the season containing today (Europe/Rome), otherwise the next one by
 *   start date, otherwise the last one. The go-live (24/5/2027) comes before the start of
 *   2027-28: without "the next one" the selector would have no default.
 * - No season at all: null, the selector is disabled.
 */
final class CurrentSeason
{
    public const SESSION_KEY = 'sports.season_id';

    public const TIMEZONE = 'Europe/Rome';

    private bool $resolved = false;

    private ?Season $season = null;

    public function get(): ?Season
    {
        if (! $this->resolved) {
            $this->season = $this->selected() ?? self::default();
            $this->resolved = true;
        }

        return $this->season;
    }

    public function select(Season $season): void
    {
        $this->session()?->put(self::SESSION_KEY, $season->id);
        $this->season = $season;
        $this->resolved = true;
    }

    /**
     * Default season on a given day (D-317).
     */
    public static function default(?Carbon $today = null): ?Season
    {
        $day = ($today ?? Carbon::now(self::TIMEZONE))->toDateString();

        return Season::query()->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day)->orderBy('starts_on')->first()
            ?? Season::query()->whereDate('starts_on', '>', $day)->orderBy('starts_on')->first()
            ?? Season::query()->orderByDesc('starts_on')->first();
    }

    private function selected(): ?Season
    {
        $id = $this->session()?->get(self::SESSION_KEY);

        return is_int($id) ? Season::query()->find($id) : null;
    }

    private function session(): ?Session
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        return $request->hasSession() ? $request->session() : null;
    }
}
