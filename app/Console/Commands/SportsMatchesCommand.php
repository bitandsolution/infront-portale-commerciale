<?php

namespace App\Console\Commands;

use App\Domain\Sports\Data\ClubMatchCount;
use App\Domain\Sports\Data\MatchCount;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Domain\Sports\Services\MatchCounter;
use App\Models\Club;
use App\Models\Discipline;
use App\Models\Season;
use App\Support\Text;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Read-only check of the match service (F-12; slice F1-05) from the server, until the configurator
 * uses it (F4): home matches of the clubs in a period, campaign and top matches. Nothing is written.
 *
 *   php artisan sports:matches 2027-28 LAZIO ROMA --from=01/10/2027 --to=31/03/2028
 */
final class SportsMatchesCommand extends Command
{
    protected $signature = 'sports:matches
        {season : Code of the season, e.g. 2027-28}
        {clubs* : Codes of the clubs, e.g. LAZIO ROMA}
        {--from= : Start of the contract (dd/mm/yyyy), the start of the season by default}
        {--to= : End of the contract (dd/mm/yyyy), the end of the season by default}';

    protected $description = 'Shows the home league matches of clubs in a period (read only)';

    public function handle(MatchCounter $counter): int
    {
        $code = $this->argument('season');
        $code = is_string($code) ? $code : '';
        $season = Season::query()->where('discipline_id', Discipline::default()->id)->where('code', $code)->first();

        if ($season === null) {
            $this->error(Text::get('sports.console.matches_unknown_season', ['season' => $code]));

            return self::FAILURE;
        }

        $clubs = [];

        foreach ((array) $this->argument('clubs') as $clubCode) {
            $clubCode = is_string($clubCode) ? $clubCode : '';
            $club = Club::query()->where('code', mb_strtoupper($clubCode))->first();

            if ($club === null) {
                $this->error(Text::get('sports.console.matches_unknown_club', ['club' => $clubCode]));

                return self::FAILURE;
            }

            $clubs[] = $club;
        }

        $from = $this->date('from') ?? $season->starts_on;
        $to = $this->date('to');

        if ($from === false || $to === false) {
            return self::FAILURE;
        }

        try {
            $count = $counter->count($season, $clubs, $from, $to);
        } catch (SportsActionNotAllowed $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->print($season, $count);

        return self::SUCCESS;
    }

    /**
     * @return Carbon|false|null null when the option is empty, false when it is not a date
     */
    private function date(string $option): Carbon|false|null
    {
        $value = $this->option($option);

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $parts) !== 1 || ! checkdate((int) $parts[2], (int) $parts[1], (int) $parts[3])) {
            $this->error(Text::get('sports.console.matches_invalid_date', ['date' => $value]));

            return false;
        }

        return Carbon::parse($parts[3].'-'.$parts[2].'-'.$parts[1]);
    }

    private function print(Season $season, MatchCount $count): void
    {
        $this->line(Text::get('sports.console.matches_period', [
            'season' => $season->code,
            'from' => $count->periodStart->format('d/m/Y'),
            'to' => $count->periodEnd->format('d/m/Y'),
        ]));

        $this->table(
            array_map(fn (string $key): string => Text::get('sports.console.'.$key), [
                'matches_club', 'matches_competition', 'matches_source', 'matches_count', 'matches_top', 'matches_first', 'matches_last',
            ]),
            array_map(fn (ClubMatchCount $club): array => [
                $club->club->name,
                $club->edition->competition->name,
                Text::get($club->fromCalendar ? 'sports.console.matches_calendar' : 'sports.console.matches_standard'),
                (string) $club->matches,
                $club->topMatches !== null ? (string) $club->topMatches : '—',
                $club->firstMatch?->format('d/m/Y') ?? '—',
                $club->lastMatch?->format('d/m/Y') ?? '—',
            ], $count->clubs),
        );

        $this->line(Text::get('sports.console.matches_total', ['total' => (string) $count->totalMatches]));
        $this->line($count->campaignStart !== null && $count->campaignEnd !== null
            ? Text::get('sports.console.matches_campaign', [
                'months' => (string) $count->campaignMonths,
                'from' => $count->campaignStart->format('d/m/Y'),
                'to' => $count->campaignEnd->format('d/m/Y'),
            ])
            : Text::get('sports.console.matches_campaign_none'));
        $this->line(Text::get('sports.console.matches_top_total', ['value' => match ($count->topMatchesStatus) {
            MatchCount::TOP_AVAILABLE => (string) $count->topMatches,
            MatchCount::TOP_NOT_AVAILABLE => Text::get('sports.console.matches_top_not_available'),
            default => Text::get('sports.console.matches_top_not_applicable'),
        }]));

        foreach ($count->warnings as $warning) {
            $this->warn($warning);
        }
    }
}
