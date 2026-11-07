<?php

namespace App\Http\Controllers\Clients;

use App\Domain\Clients\Actions\ChangeClientStatus;
use App\Domain\Clients\Actions\MergeClients;
use App\Domain\Clients\Actions\ReassignClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\MergeClientsRequest;
use App\Http\Requests\Clients\ReassignClientRequest;
use App\Models\Client;
use App\Models\User;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Admin actions on a client, from its page (M05 F-07, F-08, F-09; D-082, D-083, D-359):
 * reassignment to another agent, deactivation (with confirmation) and reactivation, merge into the
 * client that stays (with confirmation). The rules are in the Actions; a refusal comes back as a
 * toast (ClientsActionNotAllowed).
 */
class ClientManagementController extends Controller
{
    public function reassign(ReassignClientRequest $request, Client $client, ReassignClient $reassign): RedirectResponse
    {
        $owner = $request->owner();
        $client = $reassign->handle($client, $owner, $request->actor());

        return back()->with('toast', Toast::success(Text::get('clients.toasts.client_reassigned', [
            'client' => $client->company_name,
            'owner' => $owner->fullName(),
        ])));
    }

    public function deactivate(Request $request, Client $client, ChangeClientStatus $status): RedirectResponse
    {
        Gate::authorize('manage', $client);
        $client = $status->deactivate($client, $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('clients.toasts.client_deactivated', ['client' => $client->company_name])));
    }

    public function reactivate(Request $request, Client $client, ChangeClientStatus $status): RedirectResponse
    {
        Gate::authorize('manage', $client);
        $client = $status->reactivate($client, $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('clients.toasts.client_reactivated', ['client' => $client->company_name])));
    }

    public function merge(MergeClientsRequest $request, Client $client, MergeClients $merge): RedirectResponse
    {
        $keep = $merge->handle($client, $request->keep(), $request->actor());

        return redirect()->route('clients.edit', $keep)->with('toast', Toast::success(Text::get('clients.toasts.clients_merged', [
            'client' => $client->company_name,
            'keep' => $keep->company_name,
        ])));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
