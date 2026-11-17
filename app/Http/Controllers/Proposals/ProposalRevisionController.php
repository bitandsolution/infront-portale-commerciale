<?php

namespace App\Http\Controllers\Proposals;

use App\Domain\Proposals\Actions\CopyClubConfiguration;
use App\Domain\Proposals\Actions\DiscardRevision;
use App\Domain\Proposals\Actions\SaveDraft;
use App\Domain\Proposals\Actions\SaveNewVersion;
use App\Domain\Proposals\Actions\StartRevision;
use App\Http\Requests\Proposals\RevisionCopyClubsRequest;
use App\Http\Requests\Proposals\RevisionDraftRequest;
use App\Http\Requests\Proposals\SaveVersionRequest;
use App\Models\Proposal;
use App\Support\Proposals\ProposalsPresenter;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

/**
 * New version of an approved variant (M07 FL-04, F-06, RB-07, CA7; D-041, D-096, D-445, D-464…D-468;
 * F5-04): only the admin (ProposalPolicy::revise, Ability::ProposalsManageApproved).
 *
 * - "Modifica" in the detail of the group (start): the working copy is born from the current frozen
 *   version (StartRevision) and the configurator opens in review mode at step 4.
 * - The configurator (open, save, copyClubs): steps 2 to 5 as in the review of F5-02 (D-454), the
 *   residuals without the commitments of the variant (D-465), the box "Nuova versione" at step 5 (prop
 *   "revision"): the discount typed there is previewed by the server through the query of a partial
 *   reload (RevisionDiscount).
 * - "Salva la versione n+1" (saveVersion, SaveNewVersion) and "Annulla le modifiche" (discard,
 *   DiscardRevision) lead back to the detail of the group.
 */
class ProposalRevisionController extends ProposalController
{
    public const FIRST_STEP = 2;

    public const START_STEP = 4;

    public function start(Request $request, Proposal $proposal, StartRevision $startRevision): RedirectResponse
    {
        Gate::authorize('revise', $proposal);
        $created = $startRevision->handle($proposal, $this->actor($request));

        $redirect = redirect()->route('proposals.revision.edit', ['proposal' => $proposal, 'passo' => self::START_STEP]);

        return $created
            ? $redirect->with('toast', Toast::success(Text::get('proposals.toasts.revision_started', ['number' => (string) $proposal->number])))
            : $redirect;
    }

    public function open(Request $request, Proposal $proposal): Response|RedirectResponse
    {
        Gate::authorize('revise', $proposal);

        if (! $proposal->isUnderRevision()) {
            return $this->toGroup($proposal, Toast::error(Text::get('proposals.errors.not_under_revision', ['number' => (string) $proposal->number])));
        }

        $step = max(self::FIRST_STEP, min(self::SUMMARY_STEP, $request->integer('passo', self::START_STEP)));
        $evaluation = self::evaluation($proposal);

        if ($step === self::SUMMARY_STEP && $evaluation->blocksSummary()) {
            $step = self::SUMMARY_STEP - 1;
        }

        return $this->configurator($this->actor($request), $proposal, $step, $evaluation, true, true);
    }

    public function save(RevisionDraftRequest $request, Proposal $proposal, SaveDraft $saveDraft): RedirectResponse
    {
        $saveDraft->review($proposal, $request->draftData(), $request->lockVersion(), $request->actor());

        return back();
    }

    public function copyClubs(RevisionCopyClubsRequest $request, Proposal $proposal, CopyClubConfiguration $copy): RedirectResponse
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

    public function saveVersion(SaveVersionRequest $request, Proposal $proposal, SaveNewVersion $saveNewVersion): RedirectResponse
    {
        $forcedReason = $request->forcedReason();
        $version = $saveNewVersion->handle($proposal, $request->lockVersion(), $request->discount(), $request->actor(), $forcedReason);
        $values = ['number' => (string) $proposal->number, 'version' => (string) $version->number];

        return $this->toGroup($proposal, Toast::success(Text::get($version->is_forced ? 'proposals.toasts.version_forced' : 'proposals.toasts.version_saved', $values)));
    }

    public function discard(Request $request, Proposal $proposal, DiscardRevision $discardRevision): RedirectResponse
    {
        Gate::authorize('revise', $proposal);
        $discardRevision->handle($proposal, $this->actor($request));
        $proposal->refresh();

        return $this->toGroup($proposal, Toast::success(Text::get('proposals.toasts.revision_discarded', [
            'number' => (string) $proposal->number,
            'version' => (string) $proposal->currentVersion?->number,
        ])));
    }

    /**
     * @param  array<string, string>  $toast
     */
    private function toGroup(Proposal $proposal, array $toast): RedirectResponse
    {
        return $proposal->proposal_group_id !== null
            ? redirect()->route('proposals.groups.show', $proposal->proposal_group_id)->with('toast', $toast)
            : redirect()->route('pages.proposals_all')->with('toast', $toast);
    }
}
