<?php

namespace Database\Factories;

use App\Models\PartnershipTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictitious partnership tiers (not the free package). The tiers of the system data come from the
 * seeder.
 *
 * @extends Factory<PartnershipTier>
 */
class PartnershipTierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'LIVELLO_'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Livello '.fake()->unique()->word(),
            'is_free_package' => false,
            'is_active' => true,
            'sort_order' => 100,
        ];
    }
}
