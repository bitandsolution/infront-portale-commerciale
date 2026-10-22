<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Development access (D-268): local and testing environments only, removed by PR-03.
 *
 * Lists the users of the local database and signs in as one of them without a password,
 * so the menu, the area Gates and the deactivation can be checked on the VM.
 */
class DevLoginController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->orderByRaw("FIELD(role, 'admin', 'manager', 'agent')")
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->fullName(),
                'email' => $user->email,
                'role' => $user->role->label(),
                'active' => $user->is_active,
                'loginUrl' => route('dev.login.store', $user),
            ])
            ->all();

        return Inertia::render('Dev/Login', [
            'users' => $users,
            'current' => $request->user()?->getKey(),
            'logoutUrl' => route('dev.logout'),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->to(PortalPages::url('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('dev.login');
    }
}
