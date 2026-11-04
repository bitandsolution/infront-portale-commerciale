<?php

namespace App\Console\Commands;

use App\Domain\Catalog\Data\ClubQuote;
use App\Domain\Catalog\Data\ClubSelection;
use App\Domain\Catalog\Exceptions\CatalogActionNotAllowed;
use App\Domain\Catalog\Services\PriceCalculator;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Models\Club;
use App\Models\Discipline;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Season;
use App\Support\Amounts;
use App\Support\LedDuration;
use App\Support\Text;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Read-only check of the list prices of one club from the server (D-337 point 7; slice F2-06),
 * until the configurator uses PriceCalculator (F4). Nothing is written.
 *
 *   php artisan catalog:price 2027-28 LAZIO --format=FILA_1 --minutes=1,5 --tier=PREMIUM --seats=PREMIUM:2 --from=01/10/2027
 */
final class CatalogPriceCommand extends Command
{
    protected $signature = 'catalog:price
        {season : Code of the season, e.g. 2027-28}
        {club : Code of the club, e.g. LAZIO}
        {--format= : Code of the LED format, e.g. FILA_1}
        {--minutes= : LED minutes per match, e.g. 1,5 for 1′30″}
        {--tier= : Code of the partnership tier, e.g. PREMIUM or LIBERA}
        {--seats=* : Hospitality seats as VARIANT:SEATS, e.g. PREMIUM:2}
        {--from= : Start of the contract (dd/mm/yyyy), the start of the season by default}
        {--to= : End of the contract (dd/mm/yyyy), the end of the season by default}';

    protected $description = 'Shows the list prices of a club for a LED format, a partnership tier and hospitality seats (read only)';

    public function handle(PriceCalculator $calculator): int
    {
        $season = Season::query()->where('discipline_id', Discipline::default()->id)->where('code', $this->text('season'))->first();

        if ($season === null) {
            return $this->refuse('unknown_season', ['season' => $this->text('season')]);
        }

        $club = Club::query()->where('code', mb_strtoupper($this->text('club')))->first();

        if ($club === null) {
            return $this->refuse('unknown_club', ['club' => $this->text('club')]);
        }

        $format = null;
        $seconds = 0;

        if ($this->text('format', option: true) !== '') {
            $format = LedFormat::query()->where('code', mb_strtoupper($this->text('format', option: true)))->first();
            $seconds = LedDuration::parseMinutes($this->text('minutes', option: true)) ?? -1;

            if ($format === null) {
                return $this->refuse('unknown_value', ['value' => $this->text('format', option: true)]);
            }

            if ($seconds < 0) {
                return $this->refuse('invalid_minutes', ['minutes' => $this->text('minutes', option: true)]);
            }
        }

        $tier = null;

        if ($this->text('tier', option: true) !== '') {
            $tier = PartnershipTier::query()->where('code', mb_strtoupper($this->text('tier', option: true)))->first();

            if ($tier === null) {
                return $this->refuse('unknown_value', ['value' => $this->text('tier', option: true)]);
            }
        }

        $seats = [];

        foreach ((array) $this->option('seats') as $item) {
            $item = is_string($item) ? $item : '';

            if (preg_match('/^([A-Za-z0-9_]+):(\d{1,2})$/', $item, $parts) !== 1) {
                return $this->refuse('invalid_seats', ['seats' => $item]);
            }

            $variant = HospitalityVariant::query()->where('code', mb_strtoupper($parts[1]))->first();

            if ($variant === null) {
                return $this->refuse('unknown_value', ['value' => $parts[1]]);
            }

            $seats[] = ['variant' => $variant, 'seats' => (int) $parts[2]];
        }

        if ($format === null && $tier === null && $seats === []) {
            return $this->refuse('nothing');
        }

        $from = $this->date('from');
        $to = $this->date('to');

        if ($from === false || $to === false) {
            return self::FAILURE;
        }

        try {
            $quote = $calculator->quote($season, [new ClubSelection($club, $format, $seconds, $tier, $seats)], $from ?? $season->starts_on, $to);
        } catch (CatalogActionNotAllowed|SportsActionNotAllowed $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(Text::get('catalog.console.period', [
            'season' => $season->code,
            'from' => $quote->matchCount->periodStart->format('d/m/Y'),
            'to' => $quote->matchCount->periodEnd->format('d/m/Y'),
        ]));

        $this->table(
            array_map(fn (string $key): string => Text::get('catalog.console.'.$key), ['club', 'matches', 'source', 'led', 'partnership', 'hospitality', 'total']),
            array_map(fn (ClubQuote $item): array => [
                $item->club->name,
                (string) $item->matches->matches,
                Text::get($item->matches->fromCalendar ? 'catalog.console.calendar' : 'catalog.console.standard'),
                Amounts::italian($item->ledPrice).' €',
                Amounts::italian($item->partnershipPrice).' €',
                Amounts::italian($item->hospitalityPrice).' €',
                Amounts::italian($item->total).' €',
            ], $quote->clubs),
        );

        foreach ($quote->clubs as $item) {
            if ($item->ledFormat !== null && $item->ledUnitPrice !== null) {
                $this->line(Text::get('catalog.console.led_line', [
                    'format' => $item->ledFormat->name,
                    'minutes' => LedDuration::format($item->ledSeconds),
                    'unit' => Amounts::italian($item->ledUnitPrice),
                    'matches' => (string) $item->matches->matches,
                    'price' => Amounts::italian($item->ledPrice),
                ]));
            }

            if ($item->tier !== null && $item->tier->is_free_package) {
                $this->line(Text::get('catalog.console.free_package', ['tier' => $item->tier->name]));
            }

            foreach ($item->hospitality as $line) {
                $this->line(Text::get('catalog.console.seat_line', [
                    'variant' => $line->variant->name,
                    'seats' => (string) $line->seats,
                    'price' => Amounts::italian($line->seatPrice),
                ]));
            }
        }

        $this->line(Text::get('catalog.console.list_total', ['total' => Amounts::italian($quote->listTotal)]));

        foreach ($quote->warnings as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }

    private function text(string $name, bool $option = false): string
    {
        $value = $option ? $this->option($name) : $this->argument($name);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function refuse(string $key, array $replace = []): int
    {
        $this->error(Text::get('catalog.console.'.$key, $replace));

        return self::FAILURE;
    }

    /**
     * @return Carbon|false|null null when the option is empty, false when it is not a date
     */
    private function date(string $option): Carbon|false|null
    {
        $value = $this->text($option, option: true);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $parts) !== 1 || ! checkdate((int) $parts[2], (int) $parts[1], (int) $parts[3])) {
            $this->error(Text::get('catalog.console.invalid_date', ['date' => $value]));

            return false;
        }

        return Carbon::parse($parts[3].'-'.$parts[2].'-'.$parts[1]);
    }
}
