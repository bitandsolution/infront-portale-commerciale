<?php

namespace App\Http\Controllers\Proposals;

use App\Domain\Inventory\Services\SellableOptions;
use App\Domain\Proposals\Actions\CreateDraft;
use App\Domain\Proposals\Actions\DeleteDraft;
use App\Domain\Proposals\Actions\SaveDraft;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Data\DraftPriceChanges;
use App\Domain\Proposals\Services\DraftEvaluator;
use App\Domain\Proposals\Services\DraftRepricing;
use App\Domain\Proposals\Services\ProposalPeriod;
use App\Domain\Settings\Services\Settings;
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
use App\Support\Proposals\DocumentSlidesPresenter;
use App\Support\Proposals\ProposalsPresenter;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configurator pages of the agent (M06 §4; D-372…D-375; slices F4-02, F4-03, F4-04):
 * - "Nuova proposta" (proposals_new): step 1 without a draft; the draft is born when the agent
 *   chooses the client (store), never empty;
 * - a draft (edit) at step 1 (client), 2 (season and period), 3 (clubs), 4 (configuration by club)
 *   or 5 (summary: only without clubs lacking elements, D-373), saved automatically (update) with its lock version; the server evaluates the clubs and
 *   calculates prices and total in the same request; the step is in the address (?passo=2), so after
 *   an expired session the agent comes back to the same draft and step (D-375);
 * - "Le mie proposte" (proposals_mine): the agent's drafts, reopened, duplicated (F4-05, also for
 *   another client) or deleted with confirmation (D-374); the back office has no proposal pages
 *   until F5.
 * - At the reopening of a draft the prices saved on its rows are compared with those calculated now
 *   (M06 RB-12, M03-CA6): the prop "reopened" is calculated only when the page is opened, so it stays
 *   while the agent works (the automatic save reloads only "draft" and "clubOptions").
 * Every write goes through an Action with its audit event (D-372).
 */
class ProposalController extends Controller
{
    /**
     * Steps: 1 Cliente, 2 Stagione e periodo, 3 Club, 4 Configurazione, 5 Riepilogo (F4-04),
     * 6 Documento (DM-05, D-397: slides of the document and "Invia in approvazione").
     */
    public const LAST_STEP = 6;

    public const SUMMARY_STEP = 5;

    public const DOCUMENT_STEP = 6;

    public function mine(Request $request): Response
    {
        Gate::authorize('viewAny', Proposal::class);
        $actor = $this->actor($request);

        $proposals = Proposal::query()
            ->visibleTo($actor)
            ->where('agent_id', $actor->id)
            ->with(['client', 'season', 'workingVersion', 'currentVersion'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        // D-396: the variants of a group stay together, in the order of their letter; the groups in
        // the order of their most recent change. DM-03a: every status (D-411).
        $drafts = $proposals
            ->groupBy(fn (Proposal $proposal): string => $proposal->proposal_group_id !== null ? 'g'.$proposal->proposal_group_id : 'p'.$proposal->id)
            ->flatMap(/** @param Collection<int, Proposal> $variants */ function (Collection $variants): array {
                $size = $variants->count();

                return $variants
                    ->sortBy(fn (Proposal $proposal): string => $proposal->variant_letter ?? '')
                    ->map(fn (Proposal $proposal): array => ProposalsPresenter::draftRow($proposal, $size))
                    ->values()
                    ->all();
            })
            ->values()
            ->all();

        return Inertia::render('Proposals/Mine', [
            'layout' => LayoutProps::for($actor, 'proposals_mine'),
            'drafts' => $drafts,
            // "Duplica" (F4-05): the copy can be for another of the agent's clients (D-091).
            'clients' => ProposalsPresenter::clientOptions($actor),
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
        $evaluation = self::evaluation($proposal);

        // D-373: without clubs, or with a club without elements, the summary and the step
        // "Documento" after it are not available.
        if ($step >= self::SUMMARY_STEP && $evaluation->blocksSummary()) {
            $step = self::SUMMARY_STEP - 1;
        }

        return $this->configurator($this->actor($request), $proposal, $step, $evaluation);
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
        $letter = $proposal->variant_letter;
        // D-396: deleted from the configurator, a variant leads to another variant of its group.
        $sibling = $request->boolean('stay_in_group') && $proposal->proposal_group_id !== null
            ? Proposal::query()->where('proposal_group_id', $proposal->proposal_group_id)->whereKeyNot($proposal->id)->orderBy('variant_letter')->first()
            : null;
        $deleteDraft->handle($proposal, $this->actor($request));

        if ($sibling !== null) {
            return redirect()->route('proposals.edit', ['proposal' => $sibling, 'passo' => max(1, $request->integer('step', 1))])->with('toast', Toast::success(
                Text::get('proposals.toasts.variant_deleted', ['letter' => (string) $letter, 'number' => (string) $number]),
            ));
        }

        return redirect()->route(PortalPages::routeName('proposals_mine'))->with('toast', Toast::success(
            Text::get('proposals.toasts.draft_deleted', ['number' => (string) $number]),
        ));
    }

    private function configurator(User $actor, ?Proposal $proposal, int $step, ?DraftEvaluation $evaluation = null): Response
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
            'draft' => fn (): ?array => $proposal !== null ? ProposalsPresenter::draft($proposal, $actor, $evaluation ?? self::evaluation($proposal)) : null,
            // Prices recalculated since the last save (RB-12): only when the page is opened.
            'reopened' => fn (): ?array => $proposal !== null ? ProposalsPresenter::repricing(self::repricing($proposal, $evaluation ?? self::evaluation($proposal))) : null,
            // Outcome of "Copia su altri club" and of "Duplica proposta" (F4-05), flashed by
            // ProposalCopyController: like "reopened", they stay while the agent works.
            'copied' => fn (): mixed => session('proposal_copied'),
            'duplicated' => fn (): mixed => session('proposal_duplicated'),
            // Selectable clubs of the season of the draft with their options (step 3 and 4).
            // Slides of the document of the group (step 6, DM-05): reloaded with the draft, because a
            // change of the clubs changes the slides whose conditions hold.
            'documentSlides' => fn (): ?array => $proposal !== null && $proposal->group !== null ? app(DocumentSlidesPresenter::class)->forGroup($proposal->group, $actor) : null,
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
        /** @var DraftEvaluation $evaluation */
        $evaluation = app(DraftEvaluator::class)->evaluateProposal($proposal);

        return $evaluation;
    }

    private static function repricing(Proposal $proposal, DraftEvaluation $evaluation): ?DraftPriceChanges
    {
        /** @var ProposalVersion $version */
        $version = $proposal->workingVersion;

        return app(DraftRepricing::class)->compare(SaveDraft::rows($version), $version->list_total, $evaluation);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
