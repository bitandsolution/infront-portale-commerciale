<?php

namespace App\Http\Controllers\Users;

use App\Domain\Users\Actions\CreateUser;
use App\Domain\Users\Actions\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Support\Navigation\LayoutProps;
use App\Support\Text;
use App\Support\Toast;
use App\Support\Users\UserPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Users page (M01 §5; D-139: admin only): list, new user with activation link, data and role.
 * Every write goes through an Action (CLAUDE.md §4); the routes are behind the users.manage Gate
 * and each method checks the UserPolicy as well.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $user): array => UserPresenter::row($user))
            ->values()
            ->all();

        return Inertia::render('Users/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'users'),
            'users' => $users,
            'roles' => UserPresenter::roleOptions(),
            'createUrl' => route('users.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'layout' => LayoutProps::for($this->actor($request), 'users'),
            'roles' => UserPresenter::roleOptions(),
            'storeUrl' => route('users.store'),
            'indexUrl' => route('pages.users'),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $user = $createUser->handle($request->newUserData(), $this->actor($request));

        return redirect()->route('users.show', $user)->with('toast', Toast::success(
            Text::get('users.toasts.created', ['email' => $user->email]),
        ));
    }

    public function show(Request $request, User $user): Response
    {
        Gate::authorize('view', $user);

        $actor = $this->actor($request);
        $isSelf = $actor->is($user);
        $active = $user->is_active;
        $twoFactor = $user->two_factor_confirmed_at !== null;

        return Inertia::render('Users/Show', [
            'layout' => LayoutProps::for($actor, 'users'),
            'user' => [
                ...UserPresenter::row($user),
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'phone' => $user->phone ?? '',
                'createdAt' => UserPresenter::dateTime($user->created_at),
                'activatedAt' => UserPresenter::dateTime($user->activated_at),
                'deactivatedAt' => UserPresenter::dateTime($user->deactivated_at),
                'deactivatedBy' => $user->deactivatedBy?->fullName(),
            ],
            'roles' => UserPresenter::roleOptions(),
            'isSelf' => $isSelf,
            'actions' => [
                'update' => route('users.update', $user),
                'deactivate' => $active && ! $isSelf ? route('users.deactivate', $user) : null,
                'reactivate' => ! $active ? route('users.reactivate', $user) : null,
                'resetPassword' => $active && $twoFactor && ! $isSelf ? route('users.reset-password', $user) : null,
                'resetTwoFactor' => $active && $twoFactor && ! $isSelf ? route('users.reset-two-factor', $user) : null,
                'resendLink' => $active && ! $twoFactor && ! $isSelf ? route('users.resend-link', $user) : null,
            ],
            'profileUrl' => $isSelf ? route('pages.profile') : null,
            'indexUrl' => route('pages.users'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->handle($user, $request->detailsData(), $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('users.toasts.updated')));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
