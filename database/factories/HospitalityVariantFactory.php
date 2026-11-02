<?php

namespace Database\Factories;

use App\Models\HospitalityVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictitious hospitality variants. PREMIUM and BASE come from the seeder.
 *
 * @extends Factory<HospitalityVariant>
 */
class HospitalityVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'VARIANTE_'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Variante '.fake()->unique()->word(),
            'is_active' => true,
            'sort_order' => 100,
        ];
    }
}
