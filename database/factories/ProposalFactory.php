<?php

namespace Database\Factories;

use App\Domain\Proposals\Services\ProposalNumbers;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\ProposalVersion;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictitious draft proposals of an agent, for a client of the same agent (the client factory stores a
 * logo: the tests need Storage::fake(StoredFile::DISK)). The number comes from the sequence, as in the
 * Actions (D-371): the tests never assume a specific number, because a sequence is not rolled back.
 * Without withWorkingVersion() the proposal has no versions.
 *
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fn (): int => app(ProposalNumbers::class)->next(),
            'agent_id' => User::factory()->agent(),
            'client_id' => fn (array $attributes): int => Client::factory()->ownedBy(self::agent($attributes))->create()->id,
            'season_id' => Season::factory(),
            // D-396: every proposal is a variant of its group; by default variant A of a new group.
            'proposal_group_id' => fn (array $attributes): int => ProposalGroup::query()->create([
                'agent_id' => $attributes['agent_id'],
                'client_id' => $attributes['client_id'],
                'season_id' => $attributes['season_id'],
            ])->id,
            'variant_letter' => 'A',
            'status' => ProposalStatus::Draft,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function agent(array $attributes): User
    {
        $id = $attributes['agent_id'];

        return User::query()->whereKey(is_int($id) || is_string($id) ? $id : 0)->firstOrFail();
    }

    public function ownedBy(User $agent): static
    {
        return $this->state(['agent_id' => $agent->id]);
    }

    public function status(ProposalStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    /**
     * With its working version (number null), on the whole season unless the attributes say otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function withWorkingVersion(array $attributes = []): static
    {
        return $this->has(ProposalVersion::factory()->state($attributes), 'versions');
    }
}
