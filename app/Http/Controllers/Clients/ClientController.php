<?php

namespace App\Http\Controllers\Clients;

use App\Domain\Clients\Actions\CreateClient;
use App\Domain\Clients\Actions\UpdateClient;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
use App\Models\Client;
use App\Models\User;
use App\Support\Clients\ClientsPresenter;
use App\Support\Navigation\LayoutProps;
use App\Support\Navigation\PortalPages;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Client pages (M05 F-01, F-03, F-04; D-076, D-078, D-358):
 * - "I miei clienti" (clients_mine): the agent's own clients; the admin, who owns no client
 *   (D-358), goes to the full list;
 * - "Elenco clienti" (clients): every client for admin and manager, with the owner;
 * - new client (agent and admin; the admin chooses the owner), page of a client (read-only for the
 *   manager), change (owner agent and admin).
 * Every write goes through an Action; a duplicate goes back to the form with a blocking dialog
 * (DuplicateClient).
 */
class ClientController extends Controller
{
    public function mine(Request $request): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Client::class);
        $actor = $this->actor($request);

        if ($actor->role === Role::Admin) {
            return redirect()->route(PortalPages::routeName('clients'));
        }

        return $this->list($actor, 'clients_mine', withOwner: false);
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Client::class);

        return $this->list($this->actor($request), 'clients', withOwner: true);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Client::class);
        $actor = $this->actor($request);
        $isAdmin = $actor->role === Role::Admin;

        return Inertia::render('Clients/Form', [
            'layout' => LayoutProps::for($actor, $this->listPage($actor)),
            'client' => null,
            'canEdit' => true,
            'sectors' => ClientsPresenter::sectorOptions(),
            'owners' => $isAdmin ? ClientsPresenter::ownerOptions() : null,
            'saveUrl' => route('clients.store'),
            'indexUrl' => PortalPages::url($this->listPage($actor)),
            'scope' => $this->listPage($actor) === 'clients_mine' ? 'mine' : 'all',
        ]);
    }

    public function store(ClientRequest $request, CreateClient $createClient): RedirectResponse
    {
        $actor = $request->actor();
        $client = $createClient->handle($request->clientData(), $request->logo() ?? abort(422), $request->owner(), $actor);

        return redirect()->route(PortalPages::routeName($this->listPage($actor)))->with('toast', Toast::success(
            Text::get('clients.toasts.client_created', ['client' => $client->company_name]),
        ));
    }

    public function edit(Request $request, Client $client): Response
    {
        Gate::authorize('view', $client);
        $actor = $this->actor($request);
        $canEdit = $actor->can('update', $client);

        return Inertia::render('Clients/Form', [
            'layout' => LayoutProps::for($actor, $this->listPage($actor)),
            'client' => ClientsPresenter::clientForm($client),
            'canEdit' => $canEdit,
            'sectors' => ClientsPresenter::sectorOptions($client),
            'owners' => null,
            'saveUrl' => $canEdit ? route('clients.update', $client) : null,
            'indexUrl' => PortalPages::url($this->listPage($actor)),
            'scope' => $this->listPage($actor) === 'clients_mine' ? 'mine' : 'all',
        ]);
    }

    public function update(ClientRequest $request, Client $client, UpdateClient $updateClient): RedirectResponse
    {
        $changed = $updateClient->handle($client, $request->clientData(), $request->logo(), $request->actor());

        return back()->with('toast', Toast::success($changed
            ? Text::get('clients.toasts.client_updated', ['client' => $client->refresh()->company_name])
            : Text::get('clients.toasts.nothing_changed')));
    }

    private function list(User $actor, string $page, bool $withOwner): Response
    {
        $clients = Client::query()
            ->visibleTo($actor)
            ->with(['sector', 'owner'])
            ->orderBy('company_name')
            ->get()
            ->map(fn (Client $client): array => ClientsPresenter::clientRow($client, $withOwner))
            ->values()
            ->all();

        return Inertia::render('Clients/Index', [
            'layout' => LayoutProps::for($actor, $page),
            'clients' => $clients,
            'withOwner' => $withOwner,
            'createUrl' => $actor->can('create', Client::class) ? route('clients.create') : null,
        ]);
    }

    /**
     * The list page of the user: his own clients for the agent, every client for the back office.
     */
    private function listPage(User $actor): string
    {
        return $actor->role === Role::Agent ? 'clients_mine' : 'clients';
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
