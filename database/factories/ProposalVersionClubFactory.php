<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\LedFormat;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Models\Territory;
use App\Models\VisibilityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Club rows of a working version, with nothing configured yet (the draft is always saved, D-373).
 *
 * @extends Factory<ProposalVersionClub>
 */
class ProposalVersionClubFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proposal_version_id' => ProposalVersion::factory(),
            'club_id' => Club::factory()->infront(),
            'competition_season_id' => null,
            'sort_order' => 0,
            'territory_id' => Territory::factory(),
            'visibility_type_id' => VisibilityType::factory(),
        ];
    }

    /**
     * One LED format with its seconds per match (D-088).
     */
    public function led(LedFormat $format, int $seconds): static
    {
        return $this->state(['led_format_id' => $format->id, 'led_seconds_per_match' => $seconds]);
    }
}
