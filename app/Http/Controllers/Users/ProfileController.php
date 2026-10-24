<?php

namespace App\Http\Controllers\Users;

use App\Domain\Users\Actions\ChangePassword;
use App\Domain\Users\Actions\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UpdatePasswordRequest;
use App\Http\Requests\Users\UpdateProfileRequest;
use App\Models\User;
use App\Support\Navigation\LayoutProps;
use App\Support\Text;
use App\Support\Toast;
use App\Support\Users\UserPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personal profile of every user (M01 §5; D-267), from the user menu of the top bar: name,
 * surname and phone; change of the password, which closes the other sessions (D-273).
 */
class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Profile/Show', [
            'layout' => LayoutProps::for($user, 'profile'),
            'profile' => [
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'role' => $user->role->label(),
                'lastLogin' => UserPresenter::dateTime($user->last_login_at),
            ],
            'updateUrl' => route('profile.update'),
            'passwordUrl' => route('profile.password'),
        ]);
    }

    public function update(UpdateProfileRequest $request, UpdateProfile $updateProfile): RedirectResponse
    {
        $updateProfile->handle(
            $this->user($request),
            $request->string('first_name')->toString(),
            $request->string('last_name')->toString(),
            $request->string('phone')->toString(),
        );

        return back()->with('toast', Toast::success(Text::get('users.toasts.profile_updated')));
    }

    public function password(UpdatePasswordRequest $request, ChangePassword $changePassword): RedirectResponse
    {
        $changePassword->handle($this->user($request), $request->string('password')->toString());

        return back()->with('toast', Toast::success(Text::get('users.toasts.password_changed')));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
