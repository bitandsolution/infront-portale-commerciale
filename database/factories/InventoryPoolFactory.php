<?php

namespace Database\Factories;

use App\Models\InventoryPool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictitious inventory pools of the classic visibility. The pools of the system data come from the
 * seeder.
 *
 * @extends Factory<InventoryPool>
 */
class InventoryPoolFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'BACINO_'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Bacino '.fake()->unique()->word(),
            'visibility_type_id' => fn (): int => VisibilityTypeFactory::classic()->id,
            'is_active' => true,
            'sort_order' => 100,
        ];
    }
}
