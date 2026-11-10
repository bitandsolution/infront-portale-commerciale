<?php

namespace App\Http\Controllers\Proposals;

use App\Domain\Proposals\Actions\CopyClubConfiguration;
use App\Domain\Proposals\Actions\DuplicateProposal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\CopyClubsRequest;
use App\Http\Requests\Proposals\DuplicateProposalRequest;
use App\Models\Client;
use App\Models\Proposal;
use App\Support\Proposals\ProposalsPresenter;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;

/**
 * Support functions of the configurator (M06 §5; F4-05):
 * - "Copia su altri club" (step 4): back to the draft with the outcome club by club, flashed as
 *   "proposal_copied" (ProposalsPresenter::copied) and read by the configurator page;
 * - "Duplica proposta" (from "Le mie proposte"): to the new draft, with the prices recalculated and
 *   the clubs to correct flashed as "proposal_duplicated" (ProposalsPresenter::duplicated).
 */
class ProposalCopyController extends Controller
{
    public function clubs(CopyClubsRequest $request, Proposal $proposal, CopyClubConfiguration $copy): RedirectResponse
    {
        $result = $copy->handle(
            $proposal,
            $request->integer('source_club_id'),
            $request->targetClubIds(),
            $request->integer('lock_version'),
            $request->actor(),
        );

        return back()
            ->with('proposal_copied', ProposalsPresenter::copied($result))
            ->with('toast', Toast::success($result->changed
                ? Text::get('proposals.toasts.clubs_copied', ['count' => (string) count($result->copied)])
                : Text::get('proposals.toasts.clubs_unchanged')));
    }

    public function duplicate(DuplicateProposalRequest $request, Proposal $proposal, DuplicateProposal $duplicate): RedirectResponse
    {
        $client = Client::query()->find($request->integer('client_id'));

        if ($client === null) {
            return back()->withErrors(['client_id' => Text::get('proposals.errors.client_not_selectable')]);
        }

        $result = $duplicate->handle($proposal, $request->actor(), $client);
        $copy = $result->proposal;

        return redirect()
            ->route('proposals.edit', ['proposal' => $copy, 'passo' => $result->hasClubs ? 4 : 2])
            ->with('proposal_duplicated', ProposalsPresenter::duplicated($proposal, $result))
            ->with('toast', Toast::success(Text::get('proposals.toasts.duplicated', [
                'number' => (string) $copy->number,
                'original' => (string) $proposal->number,
            ])));
    }
}
