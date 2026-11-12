<?php

namespace App\Http\Controllers\Proposals;

use App\Domain\Proposals\Actions\RejectGroup;
use App\Domain\Proposals\Actions\SubmitGroup;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\RejectGroupRequest;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\User;
use App\Support\Navigation\LayoutProps;
use App\Support\Proposals\ProposalGroupsPresenter;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Groups of variants in the life cycle (M07 §4, F-01, FL-03; D-395, D-411, D-412; DM-03a):
 * - "Proposte › Da approvare" (proposals_pending): the groups waiting for approval, the oldest first,
 *   for admin and manager;
 * - the detail of a group, read only, for admin and manager and for the agent of the group;
 * - "Invia in approvazione" (agent) and "Rifiuta" (admin and manager). The approval arrives with
 *   DM-03b.
 */
class ProposalGroupController extends Controller
{
    public function pending(Request $request, ProposalGroupsPresenter $presenter): Response
    {
        Gate::authorize('review', ProposalGroup::class);

        $groups = ProposalGroup::query()
            ->whereHas('proposals', fn (Builder $query) => $query->where('status', ProposalStatus::Submitted->value))
            ->whereDoesntHave('proposals', fn (Builder $query) => $query->where('status', '<>', ProposalStatus::Submitted->value))
            ->with(['client', 'agent', 'proposals.workingVersion', 'proposals.season'])
            ->get()
            ->sortBy(fn (ProposalGroup $group): string => (string) $group->proposals->max('submitted_at'))
            ->values();

        return Inertia::render('Proposals/Pending', [
            'layout' => LayoutProps::for($this->actor($request), 'proposals_pending'),
            'groups' => $presenter->queue($groups),
        ]);
    }

    public function show(Request $request, ProposalGroup $group, ProposalGroupsPresenter $presenter): Response
    {
        Gate::authorize('view', $group);
        $actor = $this->actor($request);

        return Inertia::render('Proposals/Group', [
            'layout' => LayoutProps::for($actor, $actor->can('submit', $group) ? 'proposals_mine' : 'proposals_pending'),
            'group' => $presenter->group($group, $actor),
        ]);
    }

    public function submit(Request $request, ProposalGroup $group, SubmitGroup $submitGroup): RedirectResponse
    {
        Gate::authorize('submit', $group);
        $submitGroup->handle($group, $this->actor($request));

        return redirect()->route('proposals.groups.show', $group)->with('toast', Toast::success(
            Text::get('proposals.toasts.group_submitted', ['number' => (string) $this->firstNumber($group)]),
        ));
    }

    public function reject(RejectGroupRequest $request, ProposalGroup $group, RejectGroup $rejectGroup): RedirectResponse
    {
        $rejectGroup->handle($group, $request->reason(), $this->actor($request));

        return redirect()->route('pages.proposals_pending')->with('toast', Toast::success(
            Text::get('proposals.toasts.group_rejected', ['number' => (string) $this->firstNumber($group)]),
        ));
    }

    private function firstNumber(ProposalGroup $group): int
    {
        return (int) Proposal::query()->where('proposal_group_id', $group->id)->orderBy('variant_letter')->value('number');
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
