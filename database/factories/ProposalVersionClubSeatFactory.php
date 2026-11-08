<?php

namespace Database\Factories;

use App\Models\HospitalityVariant;
use App\Models\ProposalVersionClub;
use App\Models\ProposalVersionClubSeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Hospitality seats of a club row: two seats of a new variant, without prices.
 *
 * @extends Factory<ProposalVersionClubSeat>
 */
class ProposalVersionClubSeatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proposal_version_club_id' => ProposalVersionClub::factory(),
            'hospitality_variant_id' => HospitalityVariant::factory(),
            'seats' => 2,
        ];
    }
}
