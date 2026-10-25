<?php

namespace Database\Factories;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictitious clubs ("AC Rovereto 123"), opponents by default; infront() and topMatch() set the flags.
 *
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 9999);
        $town = fake()->lastName();

        return [
            'discipline_id' => fn (): int => DisciplineFactory::football()->id,
            'code' => 'CLUB_'.$number,
            'name' => 'AC '.$town.' '.$number,
            'short_name' => $town.' '.$number,
            'home_stadium_id' => null,
            'logo_file_id' => null,
            'primary_color' => null,
            'secondary_color' => null,
            'is_infront' => false,
            'is_top_match_opponent' => false,
            'is_active' => true,
        ];
    }

    public function infront(): static
    {
        return $this->state(['is_infront' => true]);
    }

    public function topMatch(): static
    {
        return $this->state(['is_top_match_opponent' => true]);
    }
}
