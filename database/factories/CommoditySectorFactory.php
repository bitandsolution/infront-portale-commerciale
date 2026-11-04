<?php

namespace Database\Factories;

use App\Models\CommoditySector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictitious commodity sectors.
 *
 * @extends Factory<CommoditySector>
 */
class CommoditySectorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99999);

        return [
            'code' => 'SETTORE_'.$number,
            'name' => 'Settore di prova '.$number,
            'requires_free_text' => false,
            'is_active' => true,
            'sort_order' => $number,
        ];
    }

    /**
     * The sector that asks for a free text (ALTRO_VARIE, RB-04).
     */
    public function other(): self
    {
        return $this->state(['code' => CommoditySector::OTHER, 'name' => 'Altro/Varie', 'requires_free_text' => true]);
    }
}
