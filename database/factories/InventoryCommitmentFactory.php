<?php

namespace Database\Factories;

use App\Enums\InventoryResource;
use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\InventoryCommitment;
use App\Models\InventoryPool;
use App\Models\PartnershipTier;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Commitments written by the tests (D-334): the proposals arrive with F5, so the proposal columns get
 * fictitious ids. Without a state the commitment is on a new LED pool for 60 seconds per match; the
 * period is the whole season.
 *
 * @extends Factory<InventoryCommitment>
 */
class InventoryCommitmentFactory extends Factory
{
    private static int $nextProposal = 1;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $proposal = self::$nextProposal++;

        return [
            'season_id' => Season::factory(),
            'club_id' => Club::factory()->infront(),
            'proposal_id' => $proposal,
            'proposal_version_id' => $proposal,
            'proposal_version_club_id' => $proposal,
            'resource_type' => InventoryResource::Pool,
            'inventory_pool_id' => InventoryPool::factory(),
            'partnership_tier_id' => null,
            'hospitality_variant_id' => null,
            'quantity' => 60,
            'starts_on' => fn (array $attributes): string => Season::query()->findOrFail($attributes['season_id'])->starts_on->toDateString(),
            'ends_on' => fn (array $attributes): string => Season::query()->findOrFail($attributes['season_id'])->ends_on->toDateString(),
            'is_forced' => false,
            'forced_reason' => null,
            'created_by' => User::factory()->admin(),
        ];
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
