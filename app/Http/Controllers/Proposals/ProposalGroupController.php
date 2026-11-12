<?php

namespace App\Http\Controllers\Proposals;

use App\Domain\Proposals\Actions\ApproveGroup;
use App\Domain\Proposals\Actions\RecordClientChoice;
use App\Domain\Proposals\Actions\RejectGroup;
use App\Domain\Proposals\Actions\SubmitGroup;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\ApproveGroupRequest;
use App\Http\Requests\Proposals\ChooseVariantRequest;
use App\Http\Requests\Proposals\RejectGroupRequest;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\User;
use App\Support\Navigation\LayoutProps;
use App\Support\Proposals\DiscountForm;
use App\Support\Proposals\DocumentSlidesPresenter;
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
 * - "Invia in approvazione" (agent), "Rifiuta" and "Approva" with the discounts (admin and manager,
 *   DM-03b), "Registra la scelta del cliente" on an approved group of more variants (D-410);
 * - the slides of the document (DM-05, D-397): admin and manager change them while the group waits.
 *
 * The detail previews the typed discounts: the page reloads the prop "group" with the query
 * parameter "sconti" (proposal id → type and value), calculated by the server (D-408).
 */
class ProposalGroupController extends Controller
{
    private const ALL_LIMIT = 200;

    public function pending(Request $request, ProposalGroupsPresenter $presenter): Response
    {
        Gate::authorize('review', ProposalGroup::class);

        $groups = ProposalGroup::query()
            ->whereHas('proposals', fn (Builder $query) => $query->where('status', ProposalStatus::Submitted->value))
            ->whereDoesntHave('proposals', fn (Builder $query) => $query->where('status', '<>', ProposalStatus::Submitted->value))
            ->with(['client', 'agent', 'proposals.workingVersion.clubs', 'proposals.season'])
            ->get()
            ->sortBy(fn (ProposalGroup $group): string => (string) $group->proposals->max('submitted_at'))
            ->values();

        return Inertia::render('Proposals/Pending', [
            'layout' => LayoutProps::for($this->actor($request), 'proposals_pending'),
            'groups' => $presenter->queue($groups),
        ]);
    }

    /**
     * "Proposte › Tutte" (M07 F-01; DM-03b): every group for admin and manager, the most recent first,
     * with the filter by status in the address (?stato=approved); at most the last 200 groups.
     */
    public function all(Request $request, ProposalGroupsPresenter $presenter): Response
    {
        Gate::authorize('review', ProposalGroup::class);
        $status = ProposalStatus::tryFrom((string) $request->query('stato', ''));

        $groups = ProposalGroup::query()
            ->when($status !== null, fn (Builder $query) => $query->whereHas('proposals', fn (Builder $proposals) => $proposals->where('status', $status?->value)))
            ->with(['client', 'agent', 'season', 'proposals.workingVersion.clubs.club', 'proposals.currentVersion.clubs.club'])
            ->orderByDesc(Proposal::query()->selectRaw('MAX(updated_at)')->whereColumn('proposal_group_id', 'proposal_groups.id'))
            ->orderByDesc('id')
            ->limit(self::ALL_LIMIT)
            ->get();

        return Inertia::render('Proposals/All', [
            'layout' => LayoutProps::for($this->actor($request), 'proposals_all'),
            'groups' => $presenter->list($groups),
            'status' => $status !== null ? $status->value : '',
            'statuses' => ProposalGroupsPresenter::statusOptions(),
            'limit' => self::ALL_LIMIT,
        ]);
    }

    public function show(Request $request, ProposalGroup $group, ProposalGroupsPresenter $presenter, DocumentSlidesPresenter $slides): Response
    {
        Gate::authorize('view', $group);
        $actor = $this->actor($request);
        $page = match (true) {
            $actor->can('submit', $group) => 'proposals_mine',
            ProposalGroupsPresenter::status($group->proposals()->get()) === ProposalStatus::Submitted => 'proposals_pending',
            default => 'proposals_all',
        };

        $preview = $actor->can('review', ProposalGroup::class) ? $request->input('sconti', []) : [];
        $parsed = DiscountForm::parse(is_array($preview) ? $preview : []);

        return Inertia::render('Proposals/Group', [
            'layout' => LayoutProps::for($actor, $page),
            'group' => $presenter->group($group, $actor, $parsed['discounts'], $parsed['errors']),
            // Slides of the document (DM-05, D-397): admin and manager change them while the group
            // waits, the others read them; reloaded alone after every tick.
            'documentSlides' => fn (): ?array => $slides->forGroup($group, $actor),
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

    public function approve(ApproveGroupRequest $request, ProposalGroup $group, ApproveGroup $approveGroup): RedirectResponse
    {
        $approveGroup->handle($group, $request->discounts(), $this->actor($request));

        return redirect()->route('proposals.groups.show', $group)->with('toast', Toast::success(
            Text::get('proposals.toasts.group_approved', ['number' => (string) $this->firstNumber($group)]),
        ));
    }

    public function choose(ChooseVariantRequest $request, ProposalGroup $group, RecordClientChoice $recordClientChoice): RedirectResponse
    {
        $chosen = $recordClientChoice->handle($group, $request->proposalId(), $this->actor($request));

        return redirect()->route('proposals.groups.show', $group)->with('toast', Toast::success(
            Text::get('proposals.toasts.client_chosen', ['letter' => (string) $chosen->variant_letter, 'number' => (string) $chosen->number]),
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
