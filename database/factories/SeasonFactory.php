<?php

namespace Database\Factories;

use App\Enums\SeasonStatus;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Fictitious football seasons, from 1 July to 30 June. Each factory season gets a different year
 * far in the future (2101-02, 2102-03…), so seasons never overlap; forYear() sets a real one.
 *
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    private static int $nextYear = 2101;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = self::$nextYear++;

        return [
            'discipline_id' => fn (): int => DisciplineFactory::football()->id,
            ...self::datesOf($year),
            'status' => SeasonStatus::Planned,
        ];
    }

    /**
     * Season starting on 1 July of the given year, e.g. 2027 → 2027-28.
     */
    public function forYear(int $year): static
    {
        return $this->state(fn (): array => self::datesOf($year));
    }

    public function open(): static
    {
        return $this->state(['status' => SeasonStatus::Open]);
    }

    public function closed(): static
    {
        return $this->state(['status' => SeasonStatus::Closed]);
    }

    /**
     * @return array{code: string, name: string, starts_on: Carbon, ends_on: Carbon}
     */
    private static function datesOf(int $year): array
    {
        $code = sprintf('%d-%02d', $year, ($year + 1) % 100);

        return [
            'code' => $code,
            'name' => 'Stagione '.$code,
            'starts_on' => Carbon::parse(sprintf('%d-07-01', $year)),
            'ends_on' => Carbon::parse(sprintf('%d-06-30', $year + 1)),
        ];
    }
}
