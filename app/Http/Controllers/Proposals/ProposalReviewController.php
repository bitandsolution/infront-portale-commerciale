<?php

namespace App\Http\Controllers\Proposals;

use App\Domain\Proposals\Actions\CopyClubConfiguration;
use App\Domain\Proposals\Actions\SaveDraft;
use App\Domain\Proposals\Actions\TakeReviewLock;
use App\Domain\Proposals\Services\ReviewLock;
use App\Http\Requests\Proposals\ReviewCopyClubsRequest;
use App\Http\Requests\Proposals\ReviewDraftRequest;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Support\Proposals\ProposalsPresenter;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

/**
 * Change of a variant waiting for approval (M07 §4, F-03; D-098, D-445, D-454…D-457; F5-02): admin
 * and manager open the configurator in review mode from the detail of the group ("Modifica").
 *
 * - Only who holds the lock of the work (D-446): the page takes it when it opens (as the detail of
 *   the group) and every save renews it; while another user holds it the page leads back to the
 *   group, read only.
 * - Steps 2 (dates; the season is shown, fixed), 3, 4 and 5 (D-454): no client step and no step
 *   "Documento"; the slides of the document are chosen in the detail of the group (DM-05).
 * - Separate routes from those of the agent (D-455): the agent's routes and ProposalPolicy::update
 *   stay as they are; the same page Proposals/Configurator with the prop "review".
 * - Saves with SaveDraft::review(), the copy on other clubs with CopyClubConfiguration in review mode
 *   (D-457); no variants added, no deletion, no e-mail to the agent (D-458).
 */
class ProposalReviewController extends ProposalController
{
    public const FIRST_STEP = 2;

    public function open(Request $request, Proposal $proposal, TakeReviewLock $takeReviewLock): Response|RedirectResponse
    {
        Gate::authorize('review', $proposal);
        $actor = $this->actor($request);
        /** @var ProposalGroup $group */
        $group = $proposal->group()->firstOrFail();
        $takeReviewLock->handle($group, $actor);
        $variants = $group->proposals()->get();

        if (ReviewLock::heldByOther($variants, $actor)) {
            $holder = ReviewLock::holderId($variants);

            return redirect()->route('proposals.groups.show', $group)->with('toast', Toast::error(
                Text::get('proposals.errors.review_locked', ['name' => $holder !== null ? ReviewLock::name($holder) : '']),
            ));
        }

        $step = max(self::FIRST_STEP, min(self::SUMMARY_STEP, $request->integer('passo', self::FIRST_STEP)));
        $evaluation = self::evaluation($proposal);

        if ($step === self::SUMMARY_STEP && $evaluation->blocksSummary()) {
            $step = self::SUMMARY_STEP - 1;
        }

        return $this->configurator($actor, $proposal, $step, $evaluation, true);
    }

    public function save(ReviewDraftRequest $request, Proposal $proposal, SaveDraft $saveDraft): RedirectResponse
    {
        $saveDraft->review($proposal, $request->draftData(), $request->lockVersion(), $request->actor());

        return back();
    }

    public function copyClubs(ReviewCopyClubsRequest $request, Proposal $proposal, CopyClubConfiguration $copy): RedirectResponse
    {
        $result = $copy->handle(
            $proposal,
            $request->integer('source_club_id'),
            $request->targetClubIds(),
            $request->integer('lock_version'),
            $request->actor(),
            true,
        );

        return back()
            ->with('proposal_copied', ProposalsPresenter::copied($result))
            ->with('toast', Toast::success($result->changed
                ? Text::get('proposals.toasts.clubs_copied', ['count' => (string) count($result->copied)])
                : Text::get('proposals.toasts.clubs_unchanged')));
    }
}
