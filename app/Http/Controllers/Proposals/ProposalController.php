<?php

namespace App\Http\Controllers\Proposals;

use App\Domain\Inventory\Services\SellableOptions;
use App\Domain\Proposals\Actions\CreateDraft;
use App\Domain\Proposals\Actions\DeleteDraft;
use App\Domain\Proposals\Actions\SaveDraft;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Services\DraftEvaluator;
use App\Domain\Proposals\Services\ProposalPeriod;
use App\Domain\Settings\Services\Settings;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\CreateDraftRequest;
use App\Http\Requests\Proposals\SaveDraftRequest;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\User;
use App\Support\Clients\ClientsPresenter;
use App\Support\Navigation\LayoutProps;
use App\Support\Navigation\PortalPages;
use App\Support\Proposals\ProposalsPresenter;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configurator pages of the agent (M06 §4; D-372…D-375; slices F4-02, F4-03):
 * - "Nuova proposta" (proposals_new): step 1 without a draft; the draft is born when the agent
 *   chooses the client (store), never empty;
 * - a draft (edit) at step 1 (client), 2 (season and period), 3 (clubs) or 4 (configuration by
 *   club), saved automatically (update) with its lock version; the server evaluates the clubs and
 *   calculates prices and total in the same request; the step is in the address (?passo=2), so after
 *   an expired session the agent comes back to the same draft and step (D-375);
 * - "Le mie proposte" (proposals_mine): the agent's drafts, reopened or deleted with confirmation
 *   (D-374); the back office has no proposal pages until F5.
 * Every write goes through an Action with its audit event (D-372).
 */
class ProposalController extends Controller
{
    /** Steps built so far: 1 Cliente, 2 Stagione e periodo, 3 Club, 4 Configurazione (F4-04 adds the 5th). */
    public const LAST_STEP = 4;

    public function mine(Request $request): Response
    {
        Gate::authorize('viewAny', Proposal::class);
        $actor = $this->actor($request);

        $drafts = Proposal::query()
            ->visibleTo($actor)
            ->where('agent_id', $actor->id)
            ->where('status', ProposalStatus::Draft->value)
            ->with(['client', 'season', 'workingVersion'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Proposal $proposal): array => ProposalsPresenter::draftRow($proposal))
            ->values()
            ->all();

        return Inertia::render('Proposals/Mine', [
            'layout' => LayoutProps::for($actor, 'proposals_mine'),
            'drafts' => $drafts,
            'newUrl' => PortalPages::url('proposals_new'),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Proposal::class);
        $actor = $this->actor($request);

        return $this->configurator($actor, null, 1);
    }

    public function store(CreateDraftRequest $request, CreateDraft $createDraft): RedirectResponse
    {
        $client = Client::query()->find($request->integer('client_id'));

        if ($client === null) {
            return back()->withErrors(['client_id' => Text::get('proposals.errors.client_not_selectable')]);
        }

        $proposal = $createDraft->handle($request->actor(), $client);

        return redirect()->route('proposals.edit', ['proposal' => $proposal, 'passo' => 2]);
    }

    public function edit(Request $request, Proposal $proposal): Response
    {
        Gate::authorize('update', $proposal);
        $step = max(1, min(self::LAST_STEP, $request->integer('passo', 1)));

        return $this->configurator($this->actor($request), $proposal, $step);
    }

    public function update(SaveDraftRequest $request, Proposal $proposal, SaveDraft $saveDraft): RedirectResponse
    {
        $saveDraft->handle($proposal, $request->draftData(), $request->lockVersion(), $request->actor());

        return back();
    }

    public function destroy(Request $request, Proposal $proposal, DeleteDraft $deleteDraft): RedirectResponse
    {
        Gate::authorize('delete', $proposal);
        $number = $proposal->number;
        $deleteDraft->handle($proposal, $this->actor($request));

        return redirect()->route(PortalPages::routeName('proposals_mine'))->with('toast', Toast::success(
            Text::get('proposals.toasts.draft_deleted', ['number' => (string) $number]),
        ));
    }

    private function configurator(User $actor, ?Proposal $proposal, int $step): Response
    {
        $period = app(ProposalPeriod::class);
        $settings = app(Settings::class);
        $proposal?->loadMissing(['client.sector', 'season', 'workingVersion']);
        $defaultSeason = $period->defaultSeason();
        $season = $proposal?->season;

        return Inertia::render('Proposals/Configurator', [
            'layout' => LayoutProps::for($actor, $proposal === null ? 'proposals_new' : 'proposals_mine'),
            'step' => $step,
            'lastStep' => self::LAST_STEP,
            'draft' => fn (): ?array => $proposal !== null ? ProposalsPresenter::draft($proposal, $actor, self::evaluation($proposal)) : null,
            // Selectable clubs of the season of the draft with their options (step 3 and 4).
            'clubOptions' => fn (): array => ProposalsPresenter::clubOptions($season, $season !== null ? app(SellableOptions::class)->selectableClubs($season) : []),
            'ledStepSeconds' => $settings->ledStepSeconds(),
            'hospitalityMaxSeats' => $settings->hospitalityMaxSeats(),
            'clients' => ProposalsPresenter::clientOptions($actor),
            'sectors' => ClientsPresenter::sectorOptions(),
            'seasons' => ProposalsPresenter::seasonOptions($period),
            'defaultSeasonId' => $defaultSeason !== null ? (string) $defaultSeason->id : '',
            'createUrl' => route('proposals.store'),
            'newClientUrl' => route('proposals.clients.store'),
            'mineUrl' => PortalPages::url('proposals_mine'),
        ]);
    }

    /**
     * The clubs of the draft evaluated on the current list and residuals (DraftEvaluator).
     */
    private static function evaluation(Proposal $proposal): DraftEvaluation
    {
        /** @var ProposalVersion $version */
        $version = $proposal->workingVersion;

        return app(DraftEvaluator::class)->evaluate(
            $proposal->season,
            $version->starts_on?->toDateString(),
            $version->ends_on?->toDateString(),
            SaveDraft::fromRows(SaveDraft::rows($version)),
        );
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
