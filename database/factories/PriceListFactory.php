<?php

namespace Database\Factories;

use App\Enums\PriceListStatus;
use App\Models\PriceList;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Price lists of fictitious seasons: a draft by default, published() for a published one.
 *
 * @extends Factory<PriceList>
 */
class PriceListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'status' => PriceListStatus::Draft,
            'published_at' => null,
            'published_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PriceListStatus::Published,
            'published_at' => now(),
            'published_by' => User::factory()->admin(),
        ]);
    }
}
