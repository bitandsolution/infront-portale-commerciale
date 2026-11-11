<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Proposals\Data\ProposalDuplicated;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Settings\Services\Settings;
use App\Enums\SeasonStatus;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Aggiungi variante" (D-396; slice DM, DM-01): a new variant of the group of a draft, as a copy of
 * that draft (DuplicateProposal with the group): same client and season, the dates of the draft
 * recalculated on today (D-391), its clubs and configuration with list prices, matches and residuals
 * calculated now (clubs no longer valid are copied with their error, D-392).
 *
 * - Only from a draft of the agent (ProposalPolicy::update, checked by the request) whose season is
 *   not closed (D-317).
 * - At most the variants of the settings (proposal_max_variants, D-396) in the group: the group row
 *   is locked, so two clicks at the same time cannot go over the maximum or take the same letter.
 */
final class AddVariant
{
    public function __construct(
        private readonly DuplicateProposal $duplicate,
        private readonly Settings $settings,
    ) {}

    public function handle(Proposal $proposal, User $agent): ProposalDuplicated
    {
        if (! $proposal->isDraft()) {
            throw ProposalsActionNotAllowed::notDraft($proposal->number);
        }

        $season = $proposal->season()->first();

        if ($season !== null && $season->status === SeasonStatus::Closed) {
            throw ProposalsActionNotAllowed::seasonNotSelectable();
        }

        $client = $proposal->client()->first();

        if (! $client instanceof Client || $proposal->proposal_group_id === null) {
            throw ProposalsActionNotAllowed::notDuplicable($proposal->number);
        }

        $max = $this->settings->proposalMaxVariants();

        return DB::transaction(function () use ($proposal, $agent, $client, $max): ProposalDuplicated {
            /** @var ProposalGroup $group */
            $group = ProposalGroup::query()->whereKey($proposal->proposal_group_id)->lockForUpdate()->firstOrFail();

            if ($group->proposals()->count() >= $max) {
                throw ProposalsActionNotAllowed::tooManyVariants($max);
            }

            return $this->duplicate->handle($proposal, $agent, $client, $group);
        });
    }
}
