<?php

namespace App\Domain\Proposals\Services;

use App\Models\Client;
use App\Models\ProposalGroup;
use App\Models\Season;
use App\Models\User;

/**
 * Groups of variants of a proposal (D-396; slice DM): every proposal belongs to a group, and a new
 * draft (CreateDraft, DuplicateProposal) starts a new group as its variant A. The letters go from A to
 * J (the maximum of the settings is at most 10); a new variant takes the first free letter, so the
 * letters already written on a document never change.
 */
final class ProposalVariants
{
    /** @var list<string> */
    public const LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

    public const FIRST = 'A';

    /**
     * A new group for a new draft, with the agent, client and season of its first variant.
     */
    public static function newGroup(User $agent, Client $client, ?Season $season): ProposalGroup
    {
        return ProposalGroup::query()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'season_id' => $season?->id,
        ]);
    }

    /**
     * The first letter not used in the group, or null when all are used.
     */
    public static function nextLetter(ProposalGroup $group): ?string
    {
        /** @var list<string> $used */
        $used = $group->proposals()->pluck('variant_letter')->filter(fn (mixed $letter): bool => is_string($letter))->values()->all();

        foreach (self::LETTERS as $letter) {
            if (! in_array($letter, $used, true)) {
                return $letter;
            }
        }

        return null;
    }
}
