<?php

namespace Database\Factories;

use App\Models\Proposal;
use App\Models\ProposalVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Working versions (number null) on the whole season of the proposal, with a zero list total. The
 * tests freeze a version by updating it once its rows are written, as F5 will do (D-198): rows cannot
 * be added to a frozen version.
 *
 * @extends Factory<ProposalVersion>
 */
class ProposalVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'number' => null,
            'starts_on' => fn (array $attributes): ?string => self::proposal($attributes)->season?->starts_on->toDateString(),
            'ends_on' => fn (array $attributes): ?string => self::proposal($attributes)->season?->ends_on->toDateString(),
            'list_total' => '0.00',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function proposal(array $attributes): Proposal
    {
        $id = $attributes['proposal_id'];

        return Proposal::query()->with('season')->whereKey(is_int($id) || is_string($id) ? $id : 0)->firstOrFail();
    }
}
