<?php

namespace App\Http\Controllers\Users;

use App\Domain\Users\Actions\DeactivateUser;
use App\Domain\Users\Actions\ReactivateUser;
use App\Domain\Users\Actions\ResendAccessLink;
use App\Domain\Users\Actions\ResetTwoFactor;
use App\Domain\Users\Actions\StartPasswordReset;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Status and access of a user, from the Users page (M01 §5; D-139, D-140): deactivation with
 * confirmation (ux-architettura §4.5) and reactivation, reset of the second factor or of the
 * password, new access link. The rules (never on himself, never the last active admin) are in the
 * Actions; a refusal comes back as a toast (UserActionNotAllowed).
 */
class UserAccessController extends Controller
{
    public function deactivate(Request $request, User $user, DeactivateUser $deactivate): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $deactivate->handle($user, $this->actor($request));

        return $this->done('users.toasts.deactivated', $user);
    }

    public function reactivate(Request $request, User $user, ReactivateUser $reactivate): RedirectResponse
    {
        Gate::authorize('reactivate', $user);

        $reactivate->handle($user, $this->actor($request));

        return $this->done('users.toasts.reactivated', $user);
    }

    public function resetTwoFactor(Request $request, User $user, ResetTwoFactor $resetTwoFactor): RedirectResponse
    {
        Gate::authorize('resetAccess', $user);

        $resetTwoFactor->handle($user, $this->actor($request));

        return $this->done('users.toasts.two_factor_reset', $user);
    }

    public function resetPassword(Request $request, User $user, StartPasswordReset $startPasswordReset): RedirectResponse
    {
        Gate::authorize('resetAccess', $user);

        $startPasswordReset->handle($user, $this->actor($request));

        return $this->done('users.toasts.password_reset', $user);
    }

    public function resendLink(Request $request, User $user, ResendAccessLink $resendAccessLink): RedirectResponse
    {
        Gate::authorize('resetAccess', $user);

        $resendAccessLink->handle($user, $this->actor($request));

        return $this->done('users.toasts.link_sent', $user);
    }

    private function done(string $message, User $user): RedirectResponse
    {
        return back()->with('toast', Toast::success(Text::get($message, [
            'name' => $user->fullName(),
            'email' => $user->email,
        ])));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
