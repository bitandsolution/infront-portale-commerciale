<?php

namespace Database\Factories;

use App\Enums\InventoryResource;
use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\InventoryCommitment;
use App\Models\InventoryPool;
use App\Models\PartnershipTier;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Models\Season;
use App\Models\Territory;
use App\Models\User;
use App\Models\VisibilityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Commitments written by the tests. From DM-03b the commitments have foreign keys towards the proposal,
 * its version and its club row (D-334): without them the factory creates a draft proposal of the
 * season without a client (no logo stored) with a working version and a row of the club
 * (proposalRow()). Without a state the commitment is on a new LED pool for 60 seconds per match; the
 * period is the whole season.
 *
 * @extends Factory<InventoryCommitment>
 */
class InventoryCommitmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'club_id' => Club::factory()->infront(),
            'proposal_version_club_id' => fn (array $attributes): int => self::proposalRow(
                self::season($attributes),
                Club::query()->findOrFail(self::id($attributes['club_id'])),
            )->id,
            'proposal_version_id' => fn (array $attributes): int => ProposalVersionClub::query()->findOrFail(self::id($attributes['proposal_version_club_id']))->proposal_version_id,
            'proposal_id' => fn (array $attributes): int => ProposalVersion::query()->findOrFail(self::id($attributes['proposal_version_id']))->proposal_id,
            'resource_type' => InventoryResource::Pool,
            'inventory_pool_id' => InventoryPool::factory(),
            'partnership_tier_id' => null,
            'hospitality_variant_id' => null,
            'quantity' => 60,
            'starts_on' => fn (array $attributes): string => self::season($attributes)->starts_on->toDateString(),
            'ends_on' => fn (array $attributes): string => self::season($attributes)->ends_on->toDateString(),
            'is_forced' => false,
            'forced_reason' => null,
            'created_by' => User::factory()->admin(),
        ];
    }

    /**
     * A club row of a working version of a draft proposal of the season, without a client: what a
     * commitment points to in the tests that do not go through the approval.
     */
    public static function proposalRow(Season $season, Club $club): ProposalVersionClub
    {
        $proposal = Proposal::factory()->create(['season_id' => $season->id, 'client_id' => null]);
        $version = ProposalVersion::factory()->create(['proposal_id' => $proposal->id]);

        return ProposalVersionClub::factory()->create([
            'proposal_version_id' => $version->id,
            'club_id' => $club->id,
            'territory_id' => Territory::query()->orderBy('id')->value('id') ?? Territory::factory(),
            'visibility_type_id' => VisibilityType::query()->orderBy('id')->value('id') ?? VisibilityType::factory(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function season(array $attributes): Season
    {
        return Season::query()->findOrFail(self::id($attributes['season_id']));
    }

    private static function id(mixed $value): int
    {
        return is_int($value) || is_string($value) ? (int) $value : 0;
    }

    public function forPool(InventoryPool $pool, int $seconds): static
    {
        return $this->state(['resource_type' => InventoryResource::Pool, 'inventory_pool_id' => $pool->id, 'partnership_tier_id' => null, 'hospitality_variant_id' => null, 'quantity' => $seconds]);
    }

    public function forTier(PartnershipTier $tier, int $contracts = 1): static
    {
        return $this->state(['resource_type' => InventoryResource::Partnership, 'inventory_pool_id' => null, 'partnership_tier_id' => $tier->id, 'hospitality_variant_id' => null, 'quantity' => $contracts]);
    }

    public function forVariant(HospitalityVariant $variant, int $seats): static
    {
        return $this->state(['resource_type' => InventoryResource::Hospitality, 'inventory_pool_id' => null, 'partnership_tier_id' => null, 'hospitality_variant_id' => $variant->id, 'quantity' => $seats]);
    }

    public function forced(string $reason = 'Forzatura di prova approvata dall\'admin'): static
    {
        return $this->state(['is_forced' => true, 'forced_reason' => $reason]);
    }
}
