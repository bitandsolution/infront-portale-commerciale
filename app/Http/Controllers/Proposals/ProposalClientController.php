<?php

namespace App\Http\Controllers\Proposals;

use App\Domain\Clients\Actions\CreateClient;
use App\Domain\Proposals\Actions\CreateDraft;
use App\Domain\Proposals\Actions\SaveDraft;
use App\Domain\Proposals\Data\DraftData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\ProposalClientRequest;
use App\Models\Proposal;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;

/**
 * New client created from step 1 of the configurator (M05 F-02, FL-02; D-375): CreateClient with the
 * duplicate check on the whole registry (a duplicate opens the blocking dialog, neutral for the
 * agent, and nothing is written), then the client goes on the proposal:
 * - on "Nuova proposta" the draft is born with it (CreateDraft) and the page moves to step 2;
 * - on a draft it replaces the current client (SaveDraft, with the lock version of the page).
 * The client is written in its own transaction under the registry lock (ClientRegistry): if the draft
 * cannot be written afterwards, the client stays, as if it had been created from "I miei clienti" (D-380).
 */
class ProposalClientController extends Controller
{
    public function store(ProposalClientRequest $request, CreateClient $createClient, CreateDraft $createDraft): RedirectResponse
    {
        $actor = $request->actor();
        $client = $createClient->handle($request->clientData(), $request->logo() ?? abort(422), $actor, $actor);
        $proposal = $createDraft->handle($actor, $client);

        return redirect()->route('proposals.edit', ['proposal' => $proposal, 'passo' => 2])->with('toast', Toast::success(
            Text::get('proposals.toasts.client_created', ['client' => $client->company_name]),
        ));
    }

    public function update(ProposalClientRequest $request, Proposal $proposal, CreateClient $createClient, SaveDraft $saveDraft): RedirectResponse
    {
        $actor = $request->actor();
        $client = $createClient->handle($request->clientData(), $request->logo() ?? abort(422), $actor, $actor);
        $version = $proposal->workingVersion;

        $saveDraft->handle($proposal, new DraftData(
            clientId: $client->id,
            seasonId: $proposal->season_id,
            startsOn: $version?->starts_on?->toDateString(),
            endsOn: $version?->ends_on?->toDateString(),
        ), $request->integer('lock_version'), $actor);

        return back()->with('toast', Toast::success(
            Text::get('proposals.toasts.client_created', ['client' => $client->company_name]),
        ));
    }
}
